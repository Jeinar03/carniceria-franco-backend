<?php

namespace App\Http\Livewire\PreciosEspeciales;

use App\Models\CustomerProductPrice;
use App\Models\Customers;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD del panel para los precios especiales por (cliente, producto).
 * Ver: Carnicería Franco/Spec — Precio especial por cliente.md
 */
class PreciosEspecialesController extends Component
{
    use WithPagination;

    public $pageTitle, $componentName;
    private $pagination = 10;

    public $customer_id = '';
    public $product_id = '';
    public $precio_especial;
    public $activo = 1;
    public $notas;

    public $selected_id = 0;
    public $search = '';
    public $filtroCliente = '';

    protected $paginationTheme = 'bootstrap';

    protected $listeners = [
        'deleteRow' => 'destroy',
        'resetUI' => 'resetUI',
    ];

    public function mount()
    {
        $this->pageTitle = 'Listado';
        $this->componentName = 'Precios especiales';

        // Permite entrar prefiltrado desde la tabla de Clientes: ?cliente=123
        $clienteParam = request('cliente');
        if ($clienteParam && Customers::whereKey($clienteParam)->exists()) {
            $this->filtroCliente = (string) $clienteParam;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFiltroCliente()
    {
        $this->resetPage();
    }

    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function render()
    {
        $term = trim((string) $this->search);

        $data = CustomerProductPrice::query()
            ->with([
                'customer:id,nombre,apellido',
                'product:id,nombre,codigo,precio,precio_oferta,en_oferta,unidad_venta',
            ])
            ->when($this->filtroCliente !== '', function ($q) {
                $q->where('customer_id', $this->filtroCliente);
            })
            ->when($term !== '', function ($q) use ($term) {
                $q->whereHas('product', function ($sub) use ($term) {
                    $sub->where('nombre', 'like', '%' . $term . '%')
                        ->orWhere('codigo', 'like', '%' . $term . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate($this->pagination);

        $customers = Customers::orderBy('nombre')->limit(500)->get(['id', 'nombre', 'apellido']);
        $products = Product::orderBy('nombre')->get(['id', 'nombre', 'codigo', 'precio', 'unidad_venta']);

        return view('livewire.precios-especiales.precios-especiales-controller', [
            'data' => $data,
            'customers' => $customers,
            'products' => $products,
        ])->extends('layouts.theme.app')->section('content');
    }

    public function resetUI()
    {
        $this->customer_id = '';
        $this->product_id = '';
        $this->precio_especial = null;
        $this->activo = 1;
        $this->notas = null;
        $this->selected_id = 0;
        $this->resetValidation();
        $this->resetPage();
    }

    public function edit(CustomerProductPrice $precio)
    {
        $this->selected_id = $precio->id;
        $this->customer_id = (string) $precio->customer_id;
        $this->product_id = (string) $precio->product_id;
        $this->precio_especial = $precio->precio_especial;
        $this->activo = $precio->activo ? 1 : 0;
        $this->notas = $precio->notas;
        $this->emit('show-modal', 'open!');
    }

    public function Store()
    {
        $this->validate($this->rules(), $this->messages());

        try {
            CustomerProductPrice::create([
                'customer_id' => (int) $this->customer_id,
                'product_id' => (int) $this->product_id,
                'precio_especial' => round((float) $this->precio_especial, 2),
                'activo' => (bool) $this->activo,
                'notas' => $this->notas ? trim($this->notas) : null,
                'created_by' => auth()->id(),
            ]);

            $this->resetUI();
            $this->emit('precio-added', 'Precio especial registrado');
        } catch (\Throwable $e) {
            $this->emit('precio-error', 'No se pudo registrar el precio especial.');
        }
    }

    public function Update()
    {
        $this->validate($this->rules($this->selected_id), $this->messages());

        try {
            $precio = CustomerProductPrice::findOrFail($this->selected_id);

            $precio->update([
                'customer_id' => (int) $this->customer_id,
                'product_id' => (int) $this->product_id,
                'precio_especial' => round((float) $this->precio_especial, 2),
                'activo' => (bool) $this->activo,
                'notas' => $this->notas ? trim($this->notas) : null,
            ]);

            $this->resetUI();
            $this->emit('precio-updated', 'Precio especial actualizado');
        } catch (\Throwable $e) {
            $this->emit('precio-error', 'No se pudo actualizar el precio especial.');
        }
    }

    public function toggleActivo($id)
    {
        try {
            $precio = CustomerProductPrice::findOrFail($id);
            $precio->activo = ! $precio->activo;
            $precio->save();

            $this->emit('precio-updated', $precio->activo ? 'Precio especial activado' : 'Precio especial desactivado');
        } catch (\Throwable $e) {
            $this->emit('precio-error', 'No se pudo cambiar el estado.');
        }
    }

    public function destroy($id)
    {
        try {
            CustomerProductPrice::whereKey($id)->delete();
            $this->resetUI();
            $this->emit('precio-deleted', 'Precio especial eliminado');
        } catch (\Throwable $e) {
            $this->emit('precio-error', 'No se pudo eliminar el precio especial.');
        }
    }

    private function rules(int $ignoreId = 0): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                Rule::unique('customer_product_prices', 'product_id')
                    ->where(fn ($q) => $q->where('customer_id', $this->customer_id))
                    ->ignore($ignoreId),
            ],
            'precio_especial' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
            'activo' => ['required', 'boolean'],
            'notas' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'customer_id.required' => 'Selecciona el cliente',
            'customer_id.exists' => 'El cliente no existe',
            'product_id.required' => 'Selecciona el producto',
            'product_id.exists' => 'El producto no existe',
            'product_id.unique' => 'Ese cliente ya tiene un precio especial para ese producto',
            'precio_especial.required' => 'Ingresa el precio especial',
            'precio_especial.numeric' => 'El precio debe ser un número',
            'precio_especial.gt' => 'El precio debe ser mayor a 0',
            'activo.required' => 'Selecciona el estado',
        ];
    }
}
