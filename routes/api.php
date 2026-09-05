<?php

use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\IndicadoresApiController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SitioApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Prefijo /api/v1. Autenticación: Laravel Sanctum (tokens personales del
| cliente de la tienda). Rutas divididas en:
|   - Públicas: catálogo (solo lectura), sitio, login/registro, webhook MP.
|   - Protegidas (auth:sanctum): datos y ventas del cliente. El controlador
|     valida además que el cliente sea dueño del recurso (ownership).
|
| Rutas eliminadas a propósito (quedan en el historial de git): escritura de
| catálogo (POST/PUT productos y categorías) y GET /ventas (todas las ventas).
| El panel admin es Livewire con sesión y no consume la API para eso.
*/

Route::options('/{any}', function () {
    $allowed = (array) config('cors.allowed_origins', ['*']);
    $origin = request()->headers->get('Origin');
    $allowOrigin = in_array('*', $allowed, true)
        ? '*'
        : (($origin && in_array($origin, $allowed, true)) ? $origin : ($allowed[0] ?? '*'));

    return response()->json([], 200, [
        'Access-Control-Allow-Origin'  => $allowOrigin,
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'X-Requested-With, Content-Type, X-Token-Auth, Authorization',
    ]);
})->where('any', '.*');

Route::group(['middleware' => 'cors'], function () {

    Route::prefix('v1')->group(function () {

        /*
        |------------------------------------------------------------------
        | PÚBLICAS
        |------------------------------------------------------------------
        */

        // Autenticación del cliente
        Route::post('clientes/login',    [CustomersController::class, 'login'])->name('clientes.login');
        Route::post('clientes/registro', [CustomersController::class, 'store'])->name('clientes.store');
        // Alias de compatibilidad
        Route::post('usuarios/login', [CustomersController::class, 'login']);
        Route::post('usuarios',       [CustomersController::class, 'store']);

        // Link firmado del correo de verificación (lo abre el cliente desde su bandeja)
        Route::get('clientes/email/verificar/{id}/{hash}', [CustomersController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('api.v1.clientes.verificar');

        // Catálogo (solo lectura)
        Route::prefix('categorias')->group(function () {
            Route::get('/',              [CategoriesController::class, 'index'])->name('api.categorias.index');
            Route::get('/all',           [CategoriesController::class, 'all'])->name('api.categorias.all');
            Route::get('/{id}',          [CategoriesController::class, 'show'])->name('api.categorias.show');
            Route::get('/{id}/productos', [CategoriesController::class, 'getProducts'])->name('api.categorias.productos');
        });

        Route::prefix('productos')->group(function () {
            Route::get('/',                    [ProductsController::class, 'index'])->name('api.productos.index');
            Route::get('/destacados',          [ProductsController::class, 'featured'])->name('api.productos.destacados');
            Route::get('/ofertas',             [ProductsController::class, 'offers'])->name('api.productos.ofertas');
            Route::get('/buscar',              [ProductsController::class, 'search'])->name('api.productos.buscar');
            Route::get('/categoria/{categoryId}', [ProductsController::class, 'byCategory'])->name('api.productos.categoria');
            Route::get('/{id}',                [ProductsController::class, 'show'])->name('api.productos.show');
            Route::get('/{id}/stock',          [ProductsController::class, 'checkStock'])->name('api.productos.stock');
        });

        // Configuración del sitio
        Route::get('sitio/config',  [SitioApiController::class, 'getConfig'])->name('api.sitio.config');
        Route::get('sitio/alertas', [SitioApiController::class, 'getAlertas'])->name('api.sitio.alertas');

        // Webhook de MercadoPago (server-to-server, no puede mandar token)
        Route::post('mercadopago/webhook', [MercadoPagoController::class, 'webhook']);

        // Log de acciones/visitas (anónimo, con límite de tasa)
        Route::post('historial/guardar-accion', [LogController::class, 'store'])->middleware('throttle:30,1');

        /*
        |------------------------------------------------------------------
        | PROTEGIDAS — cliente autenticado (token Sanctum)
        |------------------------------------------------------------------
        */
        Route::middleware('auth:sanctum')->group(function () {

            // Cuenta del cliente
            Route::get('clientes/data',          [CustomersController::class, 'getData'])->name('clientes.data');
            Route::get('usuarios/customer-data', [CustomersController::class, 'getData']); // alias
            Route::match(['put', 'patch'], 'clientes/{id}',        [CustomersController::class, 'update'])->name('clientes.update');
            Route::match(['put', 'patch'], 'usuarios/update/{id}', [CustomersController::class, 'update']); // alias
            Route::post('clientes/logout', [CustomersController::class, 'logout'])->name('clientes.logout');
            Route::post('clientes/email/reenviar', [CustomersController::class, 'resendVerification'])
                ->middleware('throttle:6,1')
                ->name('clientes.email.reenviar');

            // Imágenes de seguimiento del cliente
            Route::post('clientes/imagenes',                          [CustomersController::class, 'storeImages'])->name('clientes.imagenes.store');
            Route::get('clientes/{id}/imagenes',                      [CustomersController::class, 'listImages'])->name('clientes.imagenes.list');
            Route::delete('clientes/{customerId}/imagenes/{imageId}', [CustomersController::class, 'deleteImage'])->name('clientes.imagenes.delete');

            // Ventas del cliente
            Route::prefix('ventas')->group(function () {
                Route::post('/', [SalesController::class, 'store'])->name('api.ventas.store');

                Route::get('/cliente/{customerId}',                 [SalesController::class, 'getCustomerPurchases'])->name('api.ventas.cliente.historial');
                Route::get('/cliente/{customerId}/estadisticas',    [SalesController::class, 'getCustomerStats'])->name('api.ventas.cliente.estadisticas');
                Route::get('/cliente/{customerId}/recientes',       [SalesController::class, 'getRecentPurchases'])->name('api.ventas.cliente.recientes');
                Route::get('/cliente/{customerId}/recomendaciones', [SalesController::class, 'getRecommendedPurchases'])->name('api.ventas.cliente.recomendaciones');

                Route::get('/{saleId}',                          [SalesController::class, 'getPurchaseDetail'])->name('api.ventas.detalle');
                Route::post('/{saleId}/evidencia-transferencia', [SalesController::class, 'uploadTransferEvidence'])->name('api.ventas.transferencia.evidencia');
                Route::get('/{saleId}/evidencia-transferencia',  [SalesController::class, 'showTransferEvidence'])->name('api.ventas.transferencia.evidencia.show');
                Route::put('/{saleId}/cancelar',                 [SalesController::class, 'cancelPurchase'])->name('api.ventas.cancelar');
            });

            // Encuestas de indicadores por pedido
            Route::prefix('indicadores')->group(function () {
                Route::get('/pedidos/{saleId}/preguntas',   [IndicadoresApiController::class, 'preguntasPedido'])->name('api.indicadores.preguntas');
                Route::post('/pedidos/{saleId}/respuestas', [IndicadoresApiController::class, 'guardarRespuestas'])->name('api.indicadores.respuestas');
            });

            // Checkout MercadoPago
            Route::prefix('mercadopago')->group(function () {
                Route::post('/create-preference',            [MercadoPagoController::class, 'createPreference']);
                Route::get('/payment-status/{paymentId}',    [MercadoPagoController::class, 'checkPaymentStatus']);
                Route::get('/venta-by-preference/{preferenceId}', [MercadoPagoController::class, 'getVentaByPreference']);
            });
        });
    });
});
