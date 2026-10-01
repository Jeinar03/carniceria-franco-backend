<?php

use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\SalesController;
use App\Http\Livewire\Categorias\CategoriasController;
use App\Http\Livewire\Clientes\ClientesController;
use App\Http\Livewire\Dash;
use App\Http\Livewire\AvisosPedidos\AvisosPedidosController;
use App\Http\Livewire\Despachos\DespachosController;
use App\Http\Livewire\Logs\LogsController;
use App\Http\Livewire\Notificaciones\NotificacionesController;
use App\Http\Livewire\PermisosController;
use App\Http\Livewire\PreciosEspeciales\PreciosEspecialesController;
use App\Http\Livewire\Productos\ProductosController;
use App\Http\Livewire\RolesController;
use App\Http\Livewire\UsersController;
use App\Http\Livewire\Ventas\VentasController;
use App\Http\Livewire\Sitio\SitioController;
use App\Http\Livewire\Inventario\InventarioController;
use App\Http\Livewire\Indicadores\IndicadoresController;
use App\Http\Livewire\MercadoPago\MercadoPagoController as MercadoPagoLivewireController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

//Auth::routes();

Auth::routes(['register' => false]); // deshabilitamos el registro de nuevos users

Route::middleware(['auth'])->group(function () {
    // Roles del panel: Admin, Cajero y Despachador. Ver Spec - Roles Admin, Cajero y Despachador.
    Route::middleware('role:Admin')->group(function () {
        Route::get('/home', Dash::class);

        Route::prefix('admin')->group(function () {
            Route::get('categorias', CategoriasController::class);
            Route::get('productos', ProductosController::class);
            Route::get('inventario', InventarioController::class);
            Route::get('indicadores', IndicadoresController::class);
        });

        Route::prefix('clientes')->group(function () {
            Route::get('/', ClientesController::class);
            Route::get('precios-especiales', PreciosEspecialesController::class);
        });

        Route::prefix('sistema')->group(function () {
            Route::get('users', UsersController::class);
            Route::get('roles', RolesController::class);
            Route::get('permisos', PermisosController::class);
            Route::get('notificaciones', NotificacionesController::class);
            Route::get('avisos-pedidos', AvisosPedidosController::class);
            Route::get('sitio', SitioController::class);
            Route::get('mercado-pago', MercadoPagoLivewireController::class);
        });

        Route::get('logs', LogsController::class);

        Route::get('admin/inventario/entradas/{date}/pdf', [InventoryReceiptController::class, 'entriesByDate'])
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('inventory.entries.pdf');
    });

    Route::middleware('role:Admin|Cajero')->group(function () {
        Route::get('admin/ventas', VentasController::class);
    });

    Route::middleware('role:Admin|Cajero|Despachador')->group(function () {
        Route::get('clientes/despachos', DespachosController::class);

        Route::get('admin/despachos/ventas/{saleId}/evidencia-transferencia', [SalesController::class, 'showTransferEvidence'])
            ->whereNumber('saleId')
            ->name('admin.transfer-evidence.show');
    });
});
