<?php

namespace App\Http\Livewire\Inventario;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class InventarioController extends Component
{
    protected $listeners = [
        'cancelInventoryEntry' => 'cancelEntry',
    ];

    public $componentName = 'Inventario';
    public $activeTab = 'entradas';
    public $entryQuantities = [];
    public $entryNotes = '';
    public $productSearch = '';
    public $detailDate;
    public $detailType = 'entrada';
    public $selectedEntryId;
    public $editQuantity;
    public $editNotes = '';

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['entradas', 'salidas'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function openEntryModal(): void
    {
        $this->entryQuantities = [];
        $this->entryNotes = '';
        $this->productSearch = '';
        $this->resetValidation();
        $this->emit('show-entry-modal');
    }

    public function saveEntries(): void
    {
        $entries = collect($this->entryQuantities)
            ->filter(function ($quantity) {
                return is_numeric($quantity) && (float) $quantity > 0;
            });

        if ($entries->isEmpty()) {
            $this->addError('entryQuantities', 'Captura una cantidad mayor a cero en al menos un producto.');
            return;
        }

        $this->validate([
            'entryQuantities.*' => ['nullable', 'numeric', 'gt:0'],
            'entryNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'entryQuantities.*.numeric' => 'La cantidad debe ser numérica.',
            'entryQuantities.*.gt' => 'La cantidad debe ser mayor a cero.',
            'entryNotes.max' => 'Las notas no pueden superar 500 caracteres.',
        ]);

        try {
            DB::transaction(function () use ($entries) {
                $products = Product::whereIn('id', $entries->keys())
                    ->active()
                    ->orderBy('id')
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== $entries->count()) {
                    throw new \RuntimeException('Uno de los productos seleccionados ya no está disponible.');
                }

                $inventory = app(InventoryService::class);
                $receptionId = (string) Str::uuid();
                foreach ($products as $product) {
                    $inventory->addEntry(
                        $product,
                        (float) $entries->get($product->id),
                        trim((string) $this->entryNotes) ?: null,
                        auth()->id(),
                        $receptionId
                    );
                }
            });

            $count = $entries->count();
            $this->entryQuantities = [];
            $this->entryNotes = '';
            $this->emit('hide-entry-modal');
            $this->emit('inventory-saved', $count === 1
                ? 'Entrada registrada correctamente.'
                : "{$count} entradas registradas correctamente.");
        } catch (Throwable $e) {
            Log::error('Error al registrar entradas de inventario', ['error' => $e->getMessage()]);
            $this->emit('inventory-error', 'No se pudieron registrar las entradas: ' . $e->getMessage());
        }
    }

    public function showDayDetail(string $date, string $type): void
    {
        if (!in_array($type, ['entrada', 'salida'], true)) {
            return;
        }

        try {
            $this->detailDate = Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
            $this->detailType = $type;
            $this->emit('show-inventory-detail-modal');
        } catch (Throwable $e) {
            $this->emit('inventory-error', 'La fecha seleccionada no es válida.');
        }
    }

    public function openEditEntry(int $movementId): void
    {
        try {
            $movement = InventoryMovement::where('type', InventoryMovement::TYPE_ENTRY)
                ->where('origin', 'compra')
                ->where('status', InventoryMovement::STATUS_ACTIVE)
                ->findOrFail($movementId);

            $this->selectedEntryId = $movement->id;
            $this->editQuantity = (float) $movement->quantity;
            $this->editNotes = (string) ($movement->notes ?? '');
            $this->resetValidation();
            $this->emit('show-edit-entry-modal');
        } catch (Throwable $e) {
            $this->emit('inventory-error', 'La entrada ya no está disponible para edición.');
        }
    }

    public function updateEntry(): void
    {
        $this->validate([
            'selectedEntryId' => ['required', 'integer'],
            'editQuantity' => ['required', 'numeric', 'gt:0'],
            'editNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'editQuantity.required' => 'Captura la nueva cantidad.',
            'editQuantity.gt' => 'La cantidad debe ser mayor a cero.',
            'editNotes.max' => 'Las notas no pueden superar 500 caracteres.',
        ]);

        try {
            app(InventoryService::class)->editEntry(
                (int) $this->selectedEntryId,
                (float) $this->editQuantity,
                trim((string) $this->editNotes) ?: null,
                auth()->id()
            );

            $this->emit('hide-edit-entry-modal');
            $this->emit('inventory-saved', 'Entrada actualizada. El cambio quedó registrado en el historial.');
        } catch (Throwable $e) {
            Log::warning('No se pudo editar la entrada de inventario', [
                'movement_id' => $this->selectedEntryId,
                'error' => $e->getMessage(),
            ]);
            $this->emit('inventory-error', $e->getMessage());
        }
    }

    public function cancelEntry(int $movementId, ?string $reason = null): void
    {
        try {
            app(InventoryService::class)->cancelEntry(
                $movementId,
                trim((string) $reason) ?: null,
                auth()->id()
            );

            $this->emit('inventory-saved', 'Entrada cancelada y existencias actualizadas correctamente.');
        } catch (Throwable $e) {
            Log::warning('No se pudo cancelar la entrada de inventario', [
                'movement_id' => $movementId,
                'error' => $e->getMessage(),
            ]);
            $this->emit('inventory-error', $e->getMessage());
        }
    }

    public function render()
    {
        $products = Product::query()
            ->withInventoryStock()
            ->active()
            ->when(trim($this->productSearch) !== '', function ($query) {
                $query->search(trim($this->productSearch));
            })
            ->orderBy('nombre')
            ->limit(100)
            ->get();

        $entriesByDay = $this->dailySummary(InventoryMovement::TYPE_ENTRY);
        $exitsByDay = $this->dailySummary(InventoryMovement::TYPE_EXIT);

        $detailMovements = collect();
        if ($this->detailDate) {
            $detailMovements = InventoryMovement::with(['product', 'sale', 'user', 'cancelledBy', 'audits.user'])
                ->where('type', $this->detailType)
                ->whereDate('moved_at', $this->detailDate)
                ->orderBy('moved_at', 'desc')
                ->get();
        }

        return view('livewire.inventario.inventario-controller', compact(
            'products',
            'entriesByDay',
            'exitsByDay',
            'detailMovements'
        ))->extends('layouts.theme.app')->section('content');
    }

    private function dailySummary(string $type)
    {
        $rows = InventoryMovement::query()
            ->where('type', $type)
            ->selectRaw("DATE(moved_at) AS movement_date, unit, SUM(CASE WHEN status = 'active' THEN quantity ELSE 0 END) AS total_quantity, COUNT(*) AS movement_count, MAX(moved_at) AS last_movement, SUM(CASE WHEN origin = 'compra' THEN 1 ELSE 0 END) AS receipt_count")
            ->groupBy(DB::raw('DATE(moved_at)'), 'unit')
            ->orderByDesc('movement_date')
            ->limit(365)
            ->get();

        return $rows->groupBy('movement_date')->map(function ($dayRows, $date) {
            return (object) [
                'date' => $date,
                'movement_count' => $dayRows->sum('movement_count'),
                'last_movement' => $dayRows->max('last_movement'),
                'has_receipt' => $dayRows->sum('receipt_count') > 0,
                'totals' => $dayRows->map(function ($row) {
                    return (object) [
                        'unit' => $row->unit,
                        'quantity' => (float) $row->total_quantity,
                    ];
                })->values(),
            ];
        })->values();
    }
}
