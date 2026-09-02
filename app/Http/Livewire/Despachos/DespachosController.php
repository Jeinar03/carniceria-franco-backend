<?php

namespace App\Http\Livewire\Despachos;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\InventoryService;
use App\Models\Product;
use App\Models\Customers;
use Carbon\Carbon;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DespachosController extends Component
{
    use WithPagination;

    public $pageTitle, $componentName;
    public $selectedSaleId = null;
    public $saleDetails = [];
    public $search = '';
    public $filtroEstado = '';
    public $filtroCliente = '';
    public $filtroFolio = '';
    public $perPage = 15;
    public $updatingDetailId = null;
    public $transferValidationSaleId = null;
    public $transferValidationNote = '';
    public $transferValidationData = [];

    // Crear pedido
    public $createCustomerId = '';
    public $createMetodoPago = 'efectivo';
    public $createNotas = '';
    public $createDescuento = 0;
    public $productSearch = '';
    public $cart = [];

    protected $paginationTheme = 'bootstrap';

    protected $listeners = [
        'refreshDespachos' => '$refresh',
        'closeModal' => 'closeModal',
        'closeCreateOrderModal' => 'closeCreateOrderModal',
        'createOrderModalClosed' => 'closeCreateOrderModal',
        'despachoModalClosed' => 'closeModal',
        'transferValidationModalClosed' => 'closeTransferValidationModal',
    ];

    public function mount()
    {
        $this->pageTitle = 'Gestión';
        $this->componentName = 'Despachos';
    }

    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado()
    {
        $this->resetPage();
    }

    public function updatingFiltroCliente()
    {
        $this->resetPage();
    }

    public function updatingFiltroFolio()
    {
        $this->resetPage();
    }

    public function updatedPerPage($value)
    {
        $allowed = [10, 15, 25, 50];
        $value = (int) $value;

        $this->perPage = in_array($value, $allowed, true) ? $value : 15;
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->filtroEstado = '';
        $this->filtroCliente = '';
        $this->filtroFolio = '';
        $this->search = '';
        $this->resetPage();
    }

    public function openCreateOrderModal()
    {
        $this->resetCreateOrderForm();
        $this->emit('show-create-order-modal');
    }

    public function closeCreateOrderModal()
    {
        $this->resetCreateOrderForm();
    }

    public function requestCloseCreateOrderModal()
    {
        $this->emit('hide-create-order-modal');
    }

    public function addProductToCart($productId)
    {
        $product = Product::find($productId);

        if (!$product || !$product->activo) {
            $this->emit('despacho-error', 'Producto no disponible');
            return;
        }

        $currentQty = isset($this->cart[$productId]) ? (float) $this->cart[$productId]['cantidad'] : 0;
        $newQty = $currentQty + 1;

        if ($newQty > (float) $product->stock) {
            $this->emit('despacho-error', 'Stock insuficiente para ' . $product->nombre);
            return;
        }

        $precioUnitario = (float) $product->precio;
        $precioOferta = $product->en_oferta ? (float) $product->precio_oferta : null;
        $precioFinal = $precioOferta ?? $precioUnitario;

        $this->cart[$productId] = [
            'product_id' => $product->id,
            'codigo' => $product->codigo,
            'nombre' => $product->nombre,
            'unidad_venta' => $product->unidad_venta,
            'cantidad' => $newQty,
            'stock' => (float) $product->stock,
            'precio_unitario' => $precioUnitario,
            'precio_oferta' => $precioOferta,
            'precio_final' => $precioFinal,
        ];
    }

    public function increaseQty($productId)
    {
        if (!isset($this->cart[$productId])) {
            return;
        }

        $newQty = (float) $this->cart[$productId]['cantidad'] + 1;
        if ($newQty > (float) $this->cart[$productId]['stock']) {
            $this->emit('despacho-error', 'Stock insuficiente para ' . $this->cart[$productId]['nombre']);
            return;
        }

        $this->cart[$productId]['cantidad'] = $newQty;
    }

    public function decreaseQty($productId)
    {
        if (!isset($this->cart[$productId])) {
            return;
        }

        $newQty = (float) $this->cart[$productId]['cantidad'] - 1;
        if ($newQty <= 0) {
            unset($this->cart[$productId]);
            return;
        }

        $this->cart[$productId]['cantidad'] = $newQty;
    }

    public function updateQty($productId, $value)
    {
        if (!isset($this->cart[$productId])) {
            return;
        }

        $qty = (float) $value;
        if ($qty <= 0) {
            unset($this->cart[$productId]);
            return;
        }

        if ($qty > (float) $this->cart[$productId]['stock']) {
            $this->emit('despacho-error', 'Stock insuficiente para ' . $this->cart[$productId]['nombre']);
            $this->cart[$productId]['cantidad'] = (float) $this->cart[$productId]['stock'];
            return;
        }

        $this->cart[$productId]['cantidad'] = $qty;
    }

    public function removeFromCart($productId)
    {
        unset($this->cart[$productId]);
    }

    public function createOrder()
    {
        $validationError = $this->validateCreateOrderInputs();
        if ($validationError !== null) {
            $this->emit('despacho-error', $validationError);
            return;
        }

        try {
            $sale = DB::transaction(function () {
                [$subtotal, $detalles] = $this->resolveOrderDetails();

                $descuento = max(0, (float) ($this->createDescuento ?? 0));
                if ($descuento > $subtotal) {
                    $descuento = $subtotal;
                }

                $impuestos = ($subtotal - $descuento) * 0.16;
                $total = $subtotal - $descuento + $impuestos;

                $sale = Sale::create([
                    'customer_id' => $this->createCustomerId,
                    'fecha_venta' => now(),
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'impuestos' => $impuestos,
                    'total' => $total,
                    'metodo_pago' => $this->createMetodoPago,
                    'estatus' => $this->createMetodoPago === 'transferencia' ? 'pendiente' : 'completada',
                    'transferencia_estado' => $this->createMetodoPago === 'transferencia' ? 'pendiente' : null,
                    'notas' => $this->createNotas,
                    'estado_envio' => 'Pendiente',
                    'usuario_id' => auth()->id(),
                ]);

                usort($detalles, function ($left, $right) {
                    return $left['product']->id <=> $right['product']->id;
                });

                foreach ($detalles as $detalle) {
                    $product = $detalle['product'];

                    $saleDetail = SaleDetail::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'cantidad' => $detalle['cantidad'],
                        'monto_pesos' => null,
                        'precio_unitario' => $detalle['precio_unitario'],
                        'precio_oferta' => $detalle['precio_oferta'],
                        'descuento' => 0,
                        'subtotal' => $detalle['subtotal'],
                        'total' => $detalle['subtotal'],
                        'producto_nombre' => $product->nombre,
                        'producto_codigo' => $product->codigo,
                        'unidad_venta' => $product->unidad_venta,
                        'estado_despacho' => 0,
                    ]);

                    app(InventoryService::class)->addSaleExit($product, $saleDetail, auth()->id());
                }

                $customer = Customers::find($this->createCustomerId);
                if ($customer) {
                    $customer->total_compras = (float) ($customer->total_compras ?? 0) + $total;
                    $customer->numero_compras = (int) ($customer->numero_compras ?? 0) + 1;
                    $customer->fecha_ultima_compra = now();
                    $customer->save();
                }

                return $sale;
            });

            OrderNotificationService::sendPurchaseCompletedNotification($sale);

            $this->emit('pedido-creado', 'Pedido creado correctamente');
            $this->emit('hide-create-order-modal');
            $this->resetCreateOrderForm();
            $this->resetPage();
        } catch (Throwable $e) {
            Log::error('Error al crear pedido desde despachos', [
                'customer_id' => $this->createCustomerId,
                'metodo_pago' => $this->createMetodoPago,
                'error' => $e->getMessage(),
            ]);

            $this->emit('despacho-error', 'Error al crear pedido: ' . $e->getMessage());
        }
    }

    public function getCartItemsCountProperty()
    {
        return count($this->cart);
    }

    public function getCartProductsCountProperty()
    {
        return array_sum(array_map(function ($item) {
            return (float) ($item['cantidad'] ?? 0);
        }, $this->cart));
    }

    public function getCartSubtotalProperty()
    {
        $subtotal = 0;
        foreach ($this->cart as $item) {
            $subtotal += ((float) $item['precio_final']) * ((float) $item['cantidad']);
        }

        return $subtotal;
    }

    public function getCartTaxesProperty()
    {
        $discount = max(0, (float) ($this->createDescuento ?? 0));
        $base = max(0, $this->cartSubtotal - $discount);

        return $base * 0.16;
    }

    public function getCartTotalProperty()
    {
        $discount = max(0, (float) ($this->createDescuento ?? 0));
        return max(0, $this->cartSubtotal - $discount) + $this->cartTaxes;
    }

    private function resetCreateOrderForm()
    {
        $this->createCustomerId = '';
        $this->createMetodoPago = 'efectivo';
        $this->createNotas = '';
        $this->createDescuento = 0;
        $this->productSearch = '';
        $this->cart = [];
    }

    public function openModal($saleId)
    {
        $sale = Sale::find($saleId);
        if (!$sale) {
            $this->emit('despacho-error', 'Venta no encontrada');
            return;
        }

        if (!$this->canManageDispatch($sale)) {
            $this->emit('despacho-error', 'Debes validar la transferencia antes de gestionar el despacho');
            return;
        }

        $this->selectedSaleId = $saleId;
        $this->loadSaleDetails();
        $this->emit('show-modal', 'open!');
    }

    public function closeModal()
    {
        $this->selectedSaleId = null;
        $this->saleDetails = [];
        $this->updatingDetailId = null;
    }

    public function requestCloseModal()
    {
        $this->emit('hide-modal');
    }

    public function openTransferValidationModal($saleId)
    {
        $sale = Sale::with('customer')->find($saleId);

        if (!$sale) {
            $this->emit('despacho-error', 'Venta no encontrada');
            return;
        }

        if ($sale->metodo_pago !== 'transferencia') {
            $this->emit('despacho-error', 'La venta no corresponde a transferencia');
            return;
        }

        $this->transferValidationSaleId = $sale->id;
        $this->transferValidationNote = '';
        $this->transferValidationData = [
            'id' => $sale->id,
            'folio' => $sale->folio,
            'customer_nombre' => $sale->customer ? trim(($sale->customer->nombre ?? '') . ' ' . ($sale->customer->apellido ?? '')) : 'Cliente General',
            'transferencia_estado' => $sale->transferencia_estado ?? 'pendiente',
            'transferencia_evidencia_path' => $sale->transferencia_evidencia_path,
            // Usar una ruta web autenticada y habilitada para vista previa same-origin.
            'transferencia_evidencia_url' => $sale->transferencia_evidencia_path
                ? route('admin.transfer-evidence.show', ['saleId' => $sale->id])
                : null,
        ];
        $this->emit('show-transfer-validation-modal');
    }

    public function closeTransferValidationModal()
    {
        $this->transferValidationSaleId = null;
        $this->transferValidationNote = '';
        $this->transferValidationData = [];
    }

    public function requestCloseTransferValidationModal()
    {
        $this->emit('hide-transfer-validation-modal');
    }

    public function approveTransfer()
    {
        $this->validateTransferDecision('aprobada');
    }

    public function rejectTransfer()
    {
        $this->validateTransferDecision('rechazada');
    }

    private function validateTransferDecision(string $decision)
    {
        if (!$this->transferValidationSaleId) {
            $this->emit('despacho-error', 'No hay una venta seleccionada para validar');
            return;
        }

        $sale = Sale::find($this->transferValidationSaleId);
        if (!$sale) {
            $this->emit('despacho-error', 'Venta no encontrada');
            return;
        }

        if ($sale->metodo_pago !== 'transferencia') {
            $this->emit('despacho-error', 'La venta no corresponde a transferencia');
            return;
        }

        if (!$sale->transferencia_evidencia_path) {
            $this->emit('despacho-error', 'El cliente no ha subido evidencia de transferencia');
            return;
        }

        $sale->transferencia_estado = $decision;
        $sale->transferencia_validada_at = now();
        $sale->transferencia_validada_por = auth()->id();

        if ($decision === 'aprobada') {
            $sale->estatus = 'completada';
        } else {
            $notaActual = trim((string) $sale->notas);
            $notaValidacion = 'Transferencia rechazada: ' . trim((string) $this->transferValidationNote ?: 'Sin observaciones');
            $sale->notas = $notaActual !== '' ? $notaActual . PHP_EOL . $notaValidacion : $notaValidacion;
            $sale->estatus = 'pendiente';
        }

        $sale->save();

        $this->transferValidationData['transferencia_estado'] = $sale->transferencia_estado;

        $this->emit('despacho-updated', $decision === 'aprobada'
            ? 'Transferencia validada correctamente'
            : 'Transferencia rechazada correctamente');
        $this->emit('hide-transfer-validation-modal');
        $this->closeTransferValidationModal();
    }

    private function loadSaleDetails()
    {
        $this->saleDetails = SaleDetail::where('sale_id', $this->selectedSaleId)
            ->with('product')
            ->get()
            ->toArray();
    }

    public function toggleProductDespacho($detailId)
    {
        $this->updatingDetailId = $detailId;

        try {
            $detail = SaleDetail::find($detailId);
            if (!$detail) {
                $this->emit('despacho-error', 'Detalle de venta no encontrado');
                return;
            }

            $sale = Sale::find($detail->sale_id);
            if (!$sale) {
                $this->emit('despacho-error', 'Venta no encontrada');
                return;
            }

            if (!$this->canManageDispatch($sale)) {
                $this->emit('despacho-error', 'Debes validar la transferencia antes de gestionar el despacho');
                return;
            }

            // Cambiar estado del producto
            $detail->estado_despacho = $detail->estado_despacho ? 0 : 1;
            $detail->save();

            // Verificar si todos los productos están despachados
            $totalProductos = $sale->details()->count();
            $productosDespachados = $sale->details()->where('estado_despacho', 1)->count();

            // Actualizar estado de la venta
            if ($productosDespachados == 0) {
                $sale->estado_envio = 'Pendiente';
            } elseif ($productosDespachados == $totalProductos) {
                $sale->estado_envio = 'Listo_para_enviar';
            } else {
                $sale->estado_envio = 'Procesando';
            }

            $sale->save();

            // En avances parciales solo actualizamos internamente; no notificamos al cliente.
            $this->emit('despacho-updated', 'Estado actualizado correctamente');

            // Recargar detalles
            $this->loadSaleDetails();

        } catch (Throwable $e) {
            Log::error('Error al actualizar estado de despacho', [
                'detail_id' => $detailId,
                'error' => $e->getMessage(),
            ]);
            $this->emit('despacho-error', 'Error: ' . $e->getMessage());
        } finally {
            $this->updatingDetailId = null;
        }
    }

    public function enviarPedido()
    {
        try {
            $sale = Sale::find($this->selectedSaleId);

            if (!$sale) {
                $this->emit('despacho-error', 'Venta no encontrada');
                return;
            }

            if (!$this->canManageDispatch($sale)) {
                $this->emit('despacho-error', 'Debes validar la transferencia antes de gestionar el despacho');
                return;
            }

            if ($sale->estado_envio !== 'Listo_para_enviar') {
                $this->emit('despacho-error', 'La venta no está lista para enviar');
                return;
            }

            // Verificar que todos los productos estén despachados
            $todosDespachados = $sale->details()->where('estado_despacho', 0)->count() == 0;

            if (!$todosDespachados) {
                $this->emit('despacho-error', 'No todos los productos están despachados');
                return;
            }

            // Cambiar estado a Enviado
            $sale->estado_envio = 'Enviado';
            $sale->save();

            // Enviar notificación de envío
            $emailSent = OrderNotificationService::sendStatusNotification($sale);

            $this->closeModal();

            if ($emailSent) {
                $this->emit('pedido-enviado', 'Pedido enviado exitosamente y cliente notificado por email');
            } else {
                $this->emit('pedido-enviado', 'Pedido enviado exitosamente (email no enviado - verificar datos del cliente)');
            }

        } catch (Throwable $e) {
            Log::error('Error al enviar pedido', [
                'sale_id' => $this->selectedSaleId,
                'error' => $e->getMessage(),
            ]);
            $this->emit('despacho-error', 'Error al enviar pedido: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = Sale::with(['customer', 'details'])
            ->whereIn('estado_envio', ['Pendiente', 'Procesando', 'Listo_para_enviar'])
            ->orderBy('fecha_venta', 'asc'); // Más viejas primero

        $query = $this->applyFilters($query);

        $ventas = $query->paginate((int) $this->perPage);

        // Calcular urgencias (más de 3 horas)
        $ventasUrgentes = [];
        foreach ($ventas as $venta) {
            $horasTranscurridas = Carbon::parse($venta->fecha_venta)->diffInHours(Carbon::now());
            if ($horasTranscurridas > 3) {
                $ventasUrgentes[] = $venta->id;
            }
        }

        $customers = Customers::orderBy('nombre')
            ->limit(300)
            ->get(['id', 'nombre', 'apellido']);

        $productSearchTerm = trim((string) $this->productSearch);

        $products = Product::where('activo', true)
            ->when($productSearchTerm !== '', function ($query) use ($productSearchTerm) {
                $query->where(function ($subQ) use ($productSearchTerm) {
                    $subQ->where('codigo', 'like', '%' . $productSearchTerm . '%')
                        ->orWhere('nombre', 'like', '%' . $productSearchTerm . '%');
                });
            })
            ->orderBy('nombre')
            ->limit(25)
            ->get(['id', 'codigo', 'nombre', 'precio', 'precio_oferta', 'en_oferta', 'stock', 'unidad_venta']);

        return view('livewire.despachos.despachos-controller', [
            'ventas' => $ventas,
            'ventasUrgentes' => $ventasUrgentes,
            'customers' => $customers,
            'products' => $products,
        ])->extends('layouts.theme.app')
            ->section('content');
    }

    private function validateCreateOrderInputs(): ?string
    {
        if (!$this->createCustomerId) {
            return 'Selecciona un cliente';
        }

        if (count($this->cart) === 0) {
            return 'Agrega productos al carrito';
        }

        if (!in_array($this->createMetodoPago, ['efectivo', 'tarjeta', 'transferencia', 'credito'], true)) {
            return 'Metodo de pago no valido';
        }

        return null;
    }

    private function resolveOrderDetails(): array
    {
        $subtotal = 0;
        $detalles = [];

        foreach ($this->cart as $item) {
            $product = Product::find($item['product_id']);

            if (!$product || !$product->activo) {
                throw new \RuntimeException('Producto no disponible: ' . ($item['nombre'] ?? 'N/A'));
            }

            $qty = (float) $item['cantidad'];
            if ($qty <= 0) {
                throw new \RuntimeException('Cantidad invalida para ' . $product->nombre);
            }

            if ((float) $product->stock < $qty) {
                throw new \RuntimeException('Stock insuficiente para ' . $product->nombre . '. Disponible: ' . $product->stock);
            }

            $precioUnitario = (float) $product->precio;
            $precioOferta = $product->en_oferta ? (float) $product->precio_oferta : null;
            $precioFinal = $precioOferta ?? $precioUnitario;
            $itemSubtotal = $precioFinal * $qty;

            $subtotal += $itemSubtotal;

            $detalles[] = [
                'product' => $product,
                'cantidad' => $qty,
                'precio_unitario' => $precioUnitario,
                'precio_oferta' => $precioOferta,
                'subtotal' => $itemSubtotal,
            ];
        }

        return [$subtotal, $detalles];
    }

    private function applyFilters($query)
    {
        $searchTerm = trim((string) $this->search);
        $filtroFolio = trim((string) $this->filtroFolio);
        $filtroCliente = trim((string) $this->filtroCliente);

        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('folio', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('customer', function ($subQ) use ($searchTerm) {
                        $subQ->where('nombre', 'like', '%' . $searchTerm . '%')
                            ->orWhere('apellido', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        if ($filtroFolio !== '') {
            $query->where('folio', 'like', '%' . $filtroFolio . '%');
        }

        if ($filtroCliente !== '') {
            $query->whereHas('customer', function ($subQ) use ($filtroCliente) {
                $subQ->where('nombre', 'like', '%' . $filtroCliente . '%')
                    ->orWhere('apellido', 'like', '%' . $filtroCliente . '%');
            });
        }

        if ($this->filtroEstado) {
            $query->where('estado_envio', $this->filtroEstado);
        }

        return $query;
    }

    public function canManageDispatch(Sale $sale): bool
    {
        if ($sale->metodo_pago !== 'transferencia') {
            return true;
        }

        return $sale->transferencia_estado === 'aprobada';
    }
}
