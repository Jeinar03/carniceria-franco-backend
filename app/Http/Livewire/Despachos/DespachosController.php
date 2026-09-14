<?php

namespace App\Http\Livewire\Despachos;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\InventoryService;
use App\Services\PricingService;
use App\Models\Product;
use App\Models\Customers;
use Carbon\Carbon;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    // Pestaña activa: 'actual' (despachos de hoy) o 'programados' (pedidos para un día posterior)
    public $activeTab = 'actual';

    // Crear pedido
    public $createCustomerId = '';
    public $createMetodoPago = 'efectivo';
    public $createNotas = '';
    public $createDescuento = 0;
    public $createFechaEntrega = '';
    public $productSearch = '';
    public $cart = [];

    // Alta rápida de cliente de mostrador (dentro del modal de Crear Orden)
    public $showNuevoCliente = false;
    public $nuevoNombre = '';
    public $nuevoApellido = '';
    public $nuevoTelefono = '';
    public $nuevoCorreo = '';

    // Editar pedido ya hecho
    public $editingSaleId = null;
    public $editCustomerId = '';
    public $editMetodoPago = 'efectivo';
    public $editNotas = '';
    public $editDescuento = 0;
    public $editFechaEntrega = '';
    public $editProductSearch = '';
    public $editCart = [];

    protected $paginationTheme = 'bootstrap';

    protected $listeners = [
        'refreshDespachos' => '$refresh',
        'closeModal' => 'closeModal',
        'closeCreateOrderModal' => 'closeCreateOrderModal',
        'createOrderModalClosed' => 'closeCreateOrderModal',
        'despachoModalClosed' => 'closeModal',
        'transferValidationModalClosed' => 'closeTransferValidationModal',
        'closeEditOrderModal' => 'closeEditOrderModal',
        'editOrderModalClosed' => 'closeEditOrderModal',
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

    public function setActiveTab($tab)
    {
        if (!in_array($tab, ['actual', 'programados'], true)) {
            return;
        }

        $this->activeTab = $tab;
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

    /**
     * Id del cliente del pedido (null = "Cliente General" / mostrador).
     */
    private function clientePedidoId(): ?int
    {
        return $this->createCustomerId ? (int) $this->createCustomerId : null;
    }

    /**
     * Al cambiar el cliente del pedido, se recalculan los precios del carrito:
     * ese cliente puede tener precios especiales en algunos productos.
     */
    public function updatedCreateCustomerId()
    {
        if (empty($this->cart)) {
            return;
        }

        $customerId = $this->clientePedidoId();
        $pricing = app(PricingService::class);

        foreach ($this->cart as $productId => $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                unset($this->cart[$productId]);
                continue;
            }

            ['precio_unitario' => $precioUnitario, 'precio_oferta' => $precioOferta]
                = $pricing->precioParaCliente($product, $customerId);
            $precioFinal = $precioOferta ?? $precioUnitario;

            $this->cart[$productId]['precio_unitario'] = $precioUnitario;
            $this->cart[$productId]['precio_oferta'] = $precioOferta;
            $this->cart[$productId]['precio_final'] = $precioFinal;

            // En modo monto se conserva el $ y se recalcula la cantidad (kg);
            // en modo cantidad no hay monto.
            if (($this->cart[$productId]['modo'] ?? 'cantidad') === 'monto') {
                $monto = (float) ($this->cart[$productId]['monto_pesos'] ?? 0);
                if ($monto > 0 && $precioFinal > 0) {
                    $this->cart[$productId]['cantidad'] = round($monto / $precioFinal, 2);
                }
            } else {
                $this->cart[$productId]['monto_pesos'] = null;
            }
        }
    }

    public function toggleNuevoCliente()
    {
        $this->showNuevoCliente = ! $this->showNuevoCliente;
        $this->resetValidation(['nuevoNombre', 'nuevoApellido', 'nuevoTelefono', 'nuevoCorreo']);
    }

    /**
     * Registra un cliente de mostrador desde el modal de Crear Orden y lo deja
     * seleccionado. Si no se da correo, se genera un placeholder @carniceria.local
     * (el cliente no entra a la tienda; solo sirve para ventas y precios especiales).
     */
    public function guardarNuevoCliente()
    {
        $this->validate([
            'nuevoNombre' => ['required', 'string', 'max:100'],
            'nuevoApellido' => ['required', 'string', 'max:100'],
            'nuevoTelefono' => ['nullable', 'string', 'max:20'],
            'nuevoCorreo' => ['nullable', 'email', 'max:150', 'unique:customers,correo'],
        ], [], [
            'nuevoNombre' => 'nombre',
            'nuevoApellido' => 'apellido',
            'nuevoTelefono' => 'teléfono',
            'nuevoCorreo' => 'correo',
        ]);

        try {
            $correo = trim((string) $this->nuevoCorreo);
            if ($correo === '') {
                $correo = 'mostrador_' . now()->format('YmdHis') . Str::lower(Str::random(4)) . '@carniceria.local';
            }

            $customer = Customers::create([
                'nombre' => trim($this->nuevoNombre),
                'apellido' => trim($this->nuevoApellido),
                'correo' => $correo,
                'telefono' => $this->nuevoTelefono ? trim($this->nuevoTelefono) : null,
                'password' => Hash::make(Str::random(32)),
                'estatus' => 'activo',
                'fecha_registro' => now(),
            ]);

            $this->createCustomerId = (string) $customer->id;
            $this->showNuevoCliente = false;
            $this->nuevoNombre = $this->nuevoApellido = $this->nuevoTelefono = $this->nuevoCorreo = '';

            $this->updatedCreateCustomerId();
            $this->emit('despacho-updated', 'Cliente registrado y seleccionado');
        } catch (Throwable $e) {
            Log::error('Error al registrar cliente de mostrador', ['error' => $e->getMessage()]);
            $this->emit('despacho-error', 'No se pudo registrar el cliente');
        }
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

        // Precio para el cliente del pedido: especial si lo tiene, si no lista/oferta.
        ['precio_unitario' => $precioUnitario, 'precio_oferta' => $precioOferta]
            = app(PricingService::class)->precioParaCliente($product, $this->clientePedidoId());
        $precioFinal = $precioOferta ?? $precioUnitario;

        // Si ya estaba en el carrito vendiendose por monto, se mantiene el modo
        // y se recalcula el monto para que quede consistente con la nueva cantidad.
        $modo = $this->cart[$productId]['modo'] ?? 'cantidad';
        $montoPesos = $modo === 'monto' ? round($precioFinal * $newQty, 2) : null;

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
            'modo' => $modo,
            'monto_pesos' => $montoPesos,
        ];
    }

    /**
     * Alterna entre vender un producto por cantidad/peso (kg) o por un monto
     * en pesos ($) que el sistema convierte a kg con el precio del producto.
     * Solo aplica a productos que se venden por kilogramo.
     */
    public function setModoVenta($productId, $modo)
    {
        if (!isset($this->cart[$productId]) || !in_array($modo, ['cantidad', 'monto'], true)) {
            return;
        }

        if ($modo === 'monto' && $this->cart[$productId]['unidad_venta'] !== 'kilogramo') {
            return;
        }

        $this->cart[$productId]['modo'] = $modo;

        if ($modo === 'monto' && empty($this->cart[$productId]['monto_pesos'])) {
            $precioFinal = (float) $this->cart[$productId]['precio_final'];
            $cantidad = (float) $this->cart[$productId]['cantidad'];
            $this->cart[$productId]['monto_pesos'] = round($precioFinal * $cantidad, 2) ?: round($precioFinal, 2);
        }
    }

    public function updateMontoPesos($productId, $value)
    {
        if (!isset($this->cart[$productId]) || ($this->cart[$productId]['modo'] ?? 'cantidad') !== 'monto') {
            return;
        }

        $monto = (float) $value;
        if ($monto <= 0) {
            $this->emit('despacho-error', 'El monto debe ser mayor a 0');
            return;
        }

        $precioFinal = (float) $this->cart[$productId]['precio_final'];
        if ($precioFinal <= 0) {
            return;
        }

        $stock = (float) $this->cart[$productId]['stock'];
        $cantidadCalculada = round($monto / $precioFinal, 2);

        if ($cantidadCalculada > $stock) {
            $montoMaximo = round($stock * $precioFinal, 2);
            $this->emit('despacho-error', 'Ese monto excede el stock de ' . $this->cart[$productId]['nombre'] . ' (max. $' . number_format($montoMaximo, 2) . ')');
            $cantidadCalculada = $stock;
            $monto = $montoMaximo;
        }

        $this->cart[$productId]['monto_pesos'] = $monto;
        $this->cart[$productId]['cantidad'] = $cantidadCalculada;
    }

    public function increaseQty($productId)
    {
        if (!isset($this->cart[$productId]) || ($this->cart[$productId]['modo'] ?? 'cantidad') === 'monto') {
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
        if (!isset($this->cart[$productId]) || ($this->cart[$productId]['modo'] ?? 'cantidad') === 'monto') {
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
        if (!isset($this->cart[$productId]) || ($this->cart[$productId]['modo'] ?? 'cantidad') === 'monto') {
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

                // Carne fresca sin procesar: IVA tasa 0% (art. 2-A LIVA).
                $impuestos = 0;
                $total = $subtotal - $descuento;

                $sale = Sale::create([
                    'customer_id' => $this->createCustomerId ?: null,
                    'fecha_venta' => now(),
                    'fecha_entrega' => $this->createFechaEntrega ?: null,
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
                        'monto_pesos' => $detalle['monto_pesos'],
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
            if (($item['modo'] ?? 'cantidad') === 'monto') {
                $subtotal += (float) ($item['monto_pesos'] ?? 0);
            } else {
                $subtotal += ((float) $item['precio_final']) * ((float) $item['cantidad']);
            }
        }

        return $subtotal;
    }

    public function getCartTaxesProperty()
    {
        // Carne fresca sin procesar: IVA tasa 0% (art. 2-A LIVA).
        return 0;
    }

    public function getCartTotalProperty()
    {
        $discount = max(0, (float) ($this->createDescuento ?? 0));
        return max(0, $this->cartSubtotal - $discount);
    }

    private function resetCreateOrderForm()
    {
        $this->createCustomerId = '';
        $this->createMetodoPago = 'efectivo';
        $this->createNotas = '';
        $this->createDescuento = 0;
        $this->createFechaEntrega = '';
        $this->productSearch = '';
        $this->cart = [];
        $this->showNuevoCliente = false;
        $this->nuevoNombre = '';
        $this->nuevoApellido = '';
        $this->nuevoTelefono = '';
        $this->nuevoCorreo = '';
    }

    /**
     * Abre el modal de edición de un pedido ya hecho, precargando el carrito
     * con sus líneas actuales para poder corregir cliente, método de pago,
     * descuento, notas, y cantidad/monto o productos de cada línea.
     */
    public function openEditOrderModal($saleId)
    {
        $sale = Sale::with('details.product')->find($saleId);

        if (!$sale) {
            $this->emit('despacho-error', 'Pedido no encontrado');
            return;
        }

        if ($sale->estatus === 'cancelada') {
            $this->emit('despacho-error', 'No se puede editar un pedido cancelado');
            return;
        }

        $this->editingSaleId = $sale->id;
        $this->editCustomerId = $sale->customer_id ? (string) $sale->customer_id : '';
        $this->editMetodoPago = $sale->metodo_pago;
        $this->editNotas = $sale->notas;
        $this->editDescuento = (float) $sale->descuento;
        $this->editFechaEntrega = $sale->fecha_entrega ? $sale->fecha_entrega->format('Y-m-d') : '';
        $this->editProductSearch = '';
        $this->editCart = [];

        foreach ($sale->details as $detail) {
            $product = $detail->product;
            // El stock ya trae descontada esta línea; se le regresa su cantidad
            // para que el tope de edición sea el stock real disponible.
            $stockDisponible = $product ? ((float) $product->stock + (float) $detail->cantidad) : (float) $detail->cantidad;

            $this->editCart[$detail->product_id] = [
                'product_id' => $detail->product_id,
                'codigo' => $detail->producto_codigo,
                'nombre' => $detail->producto_nombre,
                'unidad_venta' => $detail->unidad_venta,
                'cantidad' => (float) $detail->cantidad,
                'stock' => $stockDisponible,
                'precio_unitario' => (float) $detail->precio_unitario,
                'precio_oferta' => $detail->precio_oferta !== null ? (float) $detail->precio_oferta : null,
                'precio_final' => $detail->precio_oferta !== null ? (float) $detail->precio_oferta : (float) $detail->precio_unitario,
                'modo' => $detail->monto_pesos !== null ? 'monto' : 'cantidad',
                'monto_pesos' => $detail->monto_pesos !== null ? (float) $detail->monto_pesos : null,
            ];
        }

        $this->emit('show-edit-order-modal');
    }

    public function closeEditOrderModal()
    {
        $this->editingSaleId = null;
        $this->editCustomerId = '';
        $this->editMetodoPago = 'efectivo';
        $this->editNotas = '';
        $this->editDescuento = 0;
        $this->editFechaEntrega = '';
        $this->editProductSearch = '';
        $this->editCart = [];
    }

    public function requestCloseEditOrderModal()
    {
        $this->emit('hide-edit-order-modal');
    }

    public function updatedEditCustomerId()
    {
        if (empty($this->editCart)) {
            return;
        }

        $customerId = $this->editCustomerId ? (int) $this->editCustomerId : null;
        $pricing = app(PricingService::class);

        foreach ($this->editCart as $productId => $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                unset($this->editCart[$productId]);
                continue;
            }

            ['precio_unitario' => $precioUnitario, 'precio_oferta' => $precioOferta]
                = $pricing->precioParaCliente($product, $customerId);
            $precioFinal = $precioOferta ?? $precioUnitario;

            $this->editCart[$productId]['precio_unitario'] = $precioUnitario;
            $this->editCart[$productId]['precio_oferta'] = $precioOferta;
            $this->editCart[$productId]['precio_final'] = $precioFinal;

            if (($this->editCart[$productId]['modo'] ?? 'cantidad') === 'monto') {
                $monto = (float) ($this->editCart[$productId]['monto_pesos'] ?? 0);
                if ($monto > 0 && $precioFinal > 0) {
                    $this->editCart[$productId]['cantidad'] = round($monto / $precioFinal, 2);
                }
            } else {
                $this->editCart[$productId]['monto_pesos'] = null;
            }
        }
    }

    public function addProductToEditCart($productId)
    {
        $product = Product::find($productId);

        if (!$product || !$product->activo) {
            $this->emit('despacho-error', 'Producto no disponible');
            return;
        }

        $currentQty = isset($this->editCart[$productId]) ? (float) $this->editCart[$productId]['cantidad'] : 0;
        $newQty = $currentQty + 1;

        if ($newQty > (float) $product->stock) {
            $this->emit('despacho-error', 'Stock insuficiente para ' . $product->nombre);
            return;
        }

        $customerId = $this->editCustomerId ? (int) $this->editCustomerId : null;
        ['precio_unitario' => $precioUnitario, 'precio_oferta' => $precioOferta]
            = app(PricingService::class)->precioParaCliente($product, $customerId);
        $precioFinal = $precioOferta ?? $precioUnitario;

        $modo = $this->editCart[$productId]['modo'] ?? 'cantidad';
        $montoPesos = $modo === 'monto' ? round($precioFinal * $newQty, 2) : null;

        $this->editCart[$productId] = [
            'product_id' => $product->id,
            'codigo' => $product->codigo,
            'nombre' => $product->nombre,
            'unidad_venta' => $product->unidad_venta,
            'cantidad' => $newQty,
            'stock' => (float) $product->stock,
            'precio_unitario' => $precioUnitario,
            'precio_oferta' => $precioOferta,
            'precio_final' => $precioFinal,
            'modo' => $modo,
            'monto_pesos' => $montoPesos,
        ];
    }

    public function setEditModoVenta($productId, $modo)
    {
        if (!isset($this->editCart[$productId]) || !in_array($modo, ['cantidad', 'monto'], true)) {
            return;
        }

        if ($modo === 'monto' && $this->editCart[$productId]['unidad_venta'] !== 'kilogramo') {
            return;
        }

        $this->editCart[$productId]['modo'] = $modo;

        if ($modo === 'monto' && empty($this->editCart[$productId]['monto_pesos'])) {
            $precioFinal = (float) $this->editCart[$productId]['precio_final'];
            $cantidad = (float) $this->editCart[$productId]['cantidad'];
            $this->editCart[$productId]['monto_pesos'] = round($precioFinal * $cantidad, 2) ?: round($precioFinal, 2);
        }
    }

    public function updateEditMontoPesos($productId, $value)
    {
        if (!isset($this->editCart[$productId]) || ($this->editCart[$productId]['modo'] ?? 'cantidad') !== 'monto') {
            return;
        }

        $monto = (float) $value;
        if ($monto <= 0) {
            $this->emit('despacho-error', 'El monto debe ser mayor a 0');
            return;
        }

        $precioFinal = (float) $this->editCart[$productId]['precio_final'];
        if ($precioFinal <= 0) {
            return;
        }

        $stock = (float) $this->editCart[$productId]['stock'];
        $cantidadCalculada = round($monto / $precioFinal, 2);

        if ($cantidadCalculada > $stock) {
            $montoMaximo = round($stock * $precioFinal, 2);
            $this->emit('despacho-error', 'Ese monto excede el stock de ' . $this->editCart[$productId]['nombre'] . ' (max. $' . number_format($montoMaximo, 2) . ')');
            $cantidadCalculada = $stock;
            $monto = $montoMaximo;
        }

        $this->editCart[$productId]['monto_pesos'] = $monto;
        $this->editCart[$productId]['cantidad'] = $cantidadCalculada;
    }

    public function increaseEditQty($productId)
    {
        if (!isset($this->editCart[$productId]) || ($this->editCart[$productId]['modo'] ?? 'cantidad') === 'monto') {
            return;
        }

        $newQty = (float) $this->editCart[$productId]['cantidad'] + 1;
        if ($newQty > (float) $this->editCart[$productId]['stock']) {
            $this->emit('despacho-error', 'Stock insuficiente para ' . $this->editCart[$productId]['nombre']);
            return;
        }

        $this->editCart[$productId]['cantidad'] = $newQty;
    }

    public function decreaseEditQty($productId)
    {
        if (!isset($this->editCart[$productId]) || ($this->editCart[$productId]['modo'] ?? 'cantidad') === 'monto') {
            return;
        }

        $newQty = (float) $this->editCart[$productId]['cantidad'] - 1;
        if ($newQty <= 0) {
            unset($this->editCart[$productId]);
            return;
        }

        $this->editCart[$productId]['cantidad'] = $newQty;
    }

    public function updateEditQty($productId, $value)
    {
        if (!isset($this->editCart[$productId]) || ($this->editCart[$productId]['modo'] ?? 'cantidad') === 'monto') {
            return;
        }

        $qty = (float) $value;
        if ($qty <= 0) {
            unset($this->editCart[$productId]);
            return;
        }

        if ($qty > (float) $this->editCart[$productId]['stock']) {
            $this->emit('despacho-error', 'Stock insuficiente para ' . $this->editCart[$productId]['nombre']);
            $this->editCart[$productId]['cantidad'] = (float) $this->editCart[$productId]['stock'];
            return;
        }

        $this->editCart[$productId]['cantidad'] = $qty;
    }

    public function removeFromEditCart($productId)
    {
        unset($this->editCart[$productId]);
    }

    public function getEditCartProductsCountProperty()
    {
        return array_sum(array_map(function ($item) {
            return (float) ($item['cantidad'] ?? 0);
        }, $this->editCart));
    }

    public function getEditCartSubtotalProperty()
    {
        $subtotal = 0;
        foreach ($this->editCart as $item) {
            if (($item['modo'] ?? 'cantidad') === 'monto') {
                $subtotal += (float) ($item['monto_pesos'] ?? 0);
            } else {
                $subtotal += ((float) $item['precio_final']) * ((float) $item['cantidad']);
            }
        }

        return $subtotal;
    }

    public function getEditCartTotalProperty()
    {
        $discount = max(0, (float) ($this->editDescuento ?? 0));
        return max(0, $this->editCartSubtotal - $discount);
    }

    /**
     * Guarda las correcciones de un pedido ya hecho: agrega/quita productos,
     * ajusta cantidades/montos, cliente, método de pago, descuento y notas.
     * El inventario se ajusta línea por línea (no se recrea desde cero) para
     * mantener el historial de movimientos consistente.
     */
    public function updateOrder()
    {
        if (!$this->editingSaleId) {
            $this->emit('despacho-error', 'No hay un pedido seleccionado para editar');
            return;
        }

        $validationError = $this->validateEditOrderInputs();
        if ($validationError !== null) {
            $this->emit('despacho-error', $validationError);
            return;
        }

        try {
            DB::transaction(function () {
                $sale = Sale::with('details')->lockForUpdate()->findOrFail($this->editingSaleId);

                if ($sale->estatus === 'cancelada') {
                    throw new \RuntimeException('No se puede editar un pedido cancelado');
                }

                $totalAnterior = (float) $sale->total;
                $clienteAnteriorId = $sale->customer_id;

                $existingDetails = $sale->details->keyBy('product_id');

                [$subtotal, $detalles] = $this->resolveEditOrderDetails($existingDetails);

                $descuento = max(0, (float) ($this->editDescuento ?? 0));
                if ($descuento > $subtotal) {
                    $descuento = $subtotal;
                }
                $total = $subtotal - $descuento;

                $inventory = app(InventoryService::class);
                $newProductIds = collect($detalles)->map(fn ($d) => $d['product']->id)->all();

                // 1) Líneas que ya no están: reintegrar stock y borrar el detalle.
                foreach ($existingDetails as $productId => $detail) {
                    if (!in_array($productId, $newProductIds, true)) {
                        $product = Product::find($productId);
                        if ($product) {
                            $inventory->restoreCancelledSale($product, $detail, auth()->id());
                        }
                        $detail->delete();
                    }
                }

                // 2) Crear o actualizar líneas.
                foreach ($detalles as $detalle) {
                    $product = $detalle['product'];
                    $existing = $existingDetails->get($product->id);

                    if ($existing) {
                        $inventory->adjustSaleExit($existing, $detalle['cantidad'], auth()->id());

                        $existing->update([
                            'cantidad' => $detalle['cantidad'],
                            'monto_pesos' => $detalle['monto_pesos'],
                            'precio_unitario' => $detalle['precio_unitario'],
                            'precio_oferta' => $detalle['precio_oferta'],
                            'subtotal' => $detalle['subtotal'],
                            'total' => $detalle['subtotal'],
                        ]);
                    } else {
                        $saleDetail = SaleDetail::create([
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'cantidad' => $detalle['cantidad'],
                            'monto_pesos' => $detalle['monto_pesos'],
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

                        $inventory->addSaleExit($product, $saleDetail, auth()->id());
                    }
                }

                // 3) Cabecera del pedido.
                $nuevoCustomerId = $this->editCustomerId ?: null;
                $sale->customer_id = $nuevoCustomerId;
                $sale->metodo_pago = $this->editMetodoPago;
                $sale->notas = $this->editNotas;
                $sale->fecha_entrega = $this->editFechaEntrega ?: null;
                $sale->subtotal = $subtotal;
                $sale->descuento = $descuento;
                $sale->total = $total;

                if ($this->editMetodoPago === 'transferencia') {
                    if ($sale->transferencia_estado !== 'aprobada') {
                        $sale->transferencia_estado = $sale->transferencia_estado ?: 'pendiente';
                        $sale->estatus = 'pendiente';
                    }
                } else {
                    $sale->transferencia_estado = null;
                    $sale->estatus = 'completada';
                }

                $sale->save();

                // 4) Estadísticas del cliente (si cambió el cliente o el total).
                if ((string) $clienteAnteriorId !== (string) $nuevoCustomerId) {
                    if ($clienteAnteriorId) {
                        $clienteAnterior = Customers::find($clienteAnteriorId);
                        if ($clienteAnterior) {
                            $clienteAnterior->total_compras = max(0, (float) ($clienteAnterior->total_compras ?? 0) - $totalAnterior);
                            $clienteAnterior->numero_compras = max(0, (int) ($clienteAnterior->numero_compras ?? 0) - 1);
                            $clienteAnterior->save();
                        }
                    }
                    if ($nuevoCustomerId) {
                        $clienteNuevo = Customers::find($nuevoCustomerId);
                        if ($clienteNuevo) {
                            $clienteNuevo->total_compras = (float) ($clienteNuevo->total_compras ?? 0) + $total;
                            $clienteNuevo->numero_compras = (int) ($clienteNuevo->numero_compras ?? 0) + 1;
                            $clienteNuevo->fecha_ultima_compra = now();
                            $clienteNuevo->save();
                        }
                    }
                } elseif ($nuevoCustomerId) {
                    $cliente = Customers::find($nuevoCustomerId);
                    if ($cliente) {
                        $cliente->total_compras = max(0, (float) ($cliente->total_compras ?? 0) - $totalAnterior + $total);
                        $cliente->save();
                    }
                }
            });

            $this->emit('pedido-actualizado', 'Pedido actualizado correctamente');
            $this->emit('hide-edit-order-modal');
            $this->closeEditOrderModal();
            $this->resetPage();
        } catch (Throwable $e) {
            Log::error('Error al editar pedido', [
                'sale_id' => $this->editingSaleId,
                'error' => $e->getMessage(),
            ]);
            $this->emit('despacho-error', 'Error al editar pedido: ' . $e->getMessage());
        }
    }

    private function validateEditOrderInputs(): ?string
    {
        if (count($this->editCart) === 0) {
            return 'El pedido debe tener al menos un producto';
        }

        if (!in_array($this->editMetodoPago, ['efectivo', 'tarjeta', 'transferencia', 'credito'], true)) {
            return 'Metodo de pago no valido';
        }

        if (!$this->editCustomerId && $this->editMetodoPago === 'credito') {
            return 'Selecciona un cliente para venta a credito';
        }

        if ($this->editFechaEntrega && $this->editFechaEntrega < now()->toDateString()) {
            return 'La fecha de entrega no puede ser anterior a hoy';
        }

        return null;
    }

    /**
     * @param \Illuminate\Support\Collection<int, SaleDetail> $existingDetailsByProduct
     */
    private function resolveEditOrderDetails($existingDetailsByProduct): array
    {
        $subtotal = 0;
        $detalles = [];
        $customerId = $this->editCustomerId ? (int) $this->editCustomerId : null;

        foreach ($this->editCart as $item) {
            $product = Product::find($item['product_id']);

            if (!$product || !$product->activo) {
                throw new \RuntimeException('Producto no disponible: ' . ($item['nombre'] ?? 'N/A'));
            }

            ['precio_unitario' => $precioUnitario, 'precio_oferta' => $precioOferta]
                = app(PricingService::class)->precioParaCliente($product, $customerId);
            $precioFinal = $precioOferta ?? $precioUnitario;

            $esVentaPorMonto = ($item['modo'] ?? 'cantidad') === 'monto' && $product->unidad_venta === 'kilogramo';

            if ($esVentaPorMonto) {
                $montoPesos = (float) ($item['monto_pesos'] ?? 0);
                if ($montoPesos <= 0) {
                    throw new \RuntimeException('Monto invalido para ' . $product->nombre);
                }
                if ($precioFinal <= 0) {
                    throw new \RuntimeException('Precio invalido para ' . $product->nombre);
                }

                $qty = round($montoPesos / $precioFinal, 2);
                $itemSubtotal = $montoPesos;
            } else {
                $qty = (float) $item['cantidad'];
                $montoPesos = null;
                $itemSubtotal = $precioFinal * $qty;
            }

            if ($qty <= 0) {
                throw new \RuntimeException('Cantidad invalida para ' . $product->nombre);
            }

            // Stock real disponible: el actual + lo que esta línea ya tenía
            // reservado antes de la edición (si ya existía en el pedido).
            $yaReservado = $existingDetailsByProduct->has($product->id)
                ? (float) $existingDetailsByProduct->get($product->id)->cantidad
                : 0;
            $stockDisponible = (float) $product->stock + $yaReservado;

            if ($stockDisponible < $qty) {
                throw new \RuntimeException('Stock insuficiente para ' . $product->nombre . '. Disponible: ' . $stockDisponible);
            }

            $subtotal += $itemSubtotal;

            $detalles[] = [
                'product' => $product,
                'cantidad' => $qty,
                'monto_pesos' => $montoPesos,
                'precio_unitario' => $precioUnitario,
                'precio_oferta' => $precioOferta,
                'subtotal' => $itemSubtotal,
            ];
        }

        return [$subtotal, $detalles];
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
        $baseQuery = Sale::with(['customer', 'details'])
            ->whereIn('estado_envio', ['Pendiente', 'Procesando', 'Listo_para_enviar']);

        // Pedidos programados: fecha_entrega en un día posterior a hoy. Se
        // muestran aparte y no en la lista de despachos del día hasta que llega su fecha.
        $programadosCount = (clone $baseQuery)->programadas()->count();
        $actualCount = (clone $baseQuery)->entregaInmediata()->count();

        if ($this->activeTab === 'programados') {
            $query = (clone $baseQuery)->programadas()->orderBy('fecha_entrega', 'asc');
        } else {
            $query = (clone $baseQuery)->entregaInmediata()->orderBy('fecha_venta', 'desc'); // Más recientes primero
        }

        $query = $this->applyFilters($query);

        $ventas = $query->paginate((int) $this->perPage);

        // Calcular urgencias (más de 3 horas). Solo aplica a la pestaña de
        // despachos inmediatos; un pedido programado a futuro no es "urgente".
        $ventasUrgentes = [];
        if ($this->activeTab !== 'programados') {
            foreach ($ventas as $venta) {
                $horasTranscurridas = Carbon::parse($venta->fecha_venta)->diffInHours(Carbon::now());
                if ($horasTranscurridas > 3) {
                    $ventasUrgentes[] = $venta->id;
                }
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

        $editProductSearchTerm = trim((string) $this->editProductSearch);

        $editProducts = Product::where('activo', true)
            ->when($editProductSearchTerm !== '', function ($query) use ($editProductSearchTerm) {
                $query->where(function ($subQ) use ($editProductSearchTerm) {
                    $subQ->where('codigo', 'like', '%' . $editProductSearchTerm . '%')
                        ->orWhere('nombre', 'like', '%' . $editProductSearchTerm . '%');
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
            'editProducts' => $editProducts,
            'actualCount' => $actualCount,
            'programadosCount' => $programadosCount,
        ])->extends('layouts.theme.app')
            ->section('content');
    }

    private function validateCreateOrderInputs(): ?string
    {
        if (count($this->cart) === 0) {
            return 'Agrega productos al carrito';
        }

        if (!in_array($this->createMetodoPago, ['efectivo', 'tarjeta', 'transferencia', 'credito'], true)) {
            return 'Metodo de pago no valido';
        }

        // El credito se abona a la cuenta de un cliente real; no aplica a mostrador.
        if (!$this->createCustomerId && $this->createMetodoPago === 'credito') {
            return 'Selecciona un cliente para venta a credito';
        }

        if ($this->createFechaEntrega && $this->createFechaEntrega < now()->toDateString()) {
            return 'La fecha de entrega no puede ser anterior a hoy';
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

            // Precio fresco de la BD, con precio especial del cliente si aplica.
            ['precio_unitario' => $precioUnitario, 'precio_oferta' => $precioOferta]
                = app(PricingService::class)->precioParaCliente($product, $this->clientePedidoId());
            $precioFinal = $precioOferta ?? $precioUnitario;

            // El precio SIEMPRE se toma fresco de la BD (nunca del carrito en sesion),
            // asi que el monto->kg tambien se recalcula aqui, no se confia en el del cliente.
            $esVentaPorMonto = ($item['modo'] ?? 'cantidad') === 'monto' && $product->unidad_venta === 'kilogramo';

            if ($esVentaPorMonto) {
                $montoPesos = (float) ($item['monto_pesos'] ?? 0);
                if ($montoPesos <= 0) {
                    throw new \RuntimeException('Monto invalido para ' . $product->nombre);
                }
                if ($precioFinal <= 0) {
                    throw new \RuntimeException('Precio invalido para ' . $product->nombre);
                }

                $qty = round($montoPesos / $precioFinal, 2);
                $itemSubtotal = $montoPesos;
            } else {
                $qty = (float) $item['cantidad'];
                $montoPesos = null;
                $itemSubtotal = $precioFinal * $qty;
            }

            if ($qty <= 0) {
                throw new \RuntimeException('Cantidad invalida para ' . $product->nombre);
            }

            if ((float) $product->stock < $qty) {
                throw new \RuntimeException('Stock insuficiente para ' . $product->nombre . '. Disponible: ' . $product->stock);
            }

            $subtotal += $itemSubtotal;

            $detalles[] = [
                'product' => $product,
                'cantidad' => $qty,
                'monto_pesos' => $montoPesos,
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
