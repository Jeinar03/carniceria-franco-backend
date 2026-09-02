<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\SDK;
use MercadoPago\Preference;
use MercadoPago\Item;
use Illuminate\Support\Facades\Log;

class MercadoPagoTestController extends Controller
{
    /**
     * Prueba básica de MercadoPago
     */
    public function testBasic()
    {
        try {
            // Configurar SDK
            $accessToken = env('MERCADOPAGO_ACCESS_TOKEN');
            SDK::setAccessToken($accessToken);

            Log::info('Testing MercadoPago with token: ' . substr($accessToken, 0, 15) . '...');

            // Crear preferencia de prueba simple
            $preference = new Preference();

            // Item de prueba
            $item = new Item();
            $item->title = 'Producto de Prueba';
            $item->quantity = 1;
            $item->unit_price = 100.0;
            $item->currency_id = 'MXN';

            $preference->items = [$item];

            // URLs básicas
            $preference->back_urls = [
                "success" => "http://localhost/success",
                "failure" => "http://localhost/failure",
                "pending" => "http://localhost/pending"
            ];

            $preference->external_reference = 'TEST_' . time();

            Log::info('Attempting to save test preference...');

            // Intentar guardar
            $preference->save();

            Log::info('Test preference result:', [
                'id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point
            ]);

            return response()->json([
                'success' => true,
                'token_used' => substr($accessToken, 0, 15) . '...',
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
                'external_reference' => $preference->external_reference
            ]);

        } catch (\Exception $e) {
            Log::error('MercadoPago test failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
