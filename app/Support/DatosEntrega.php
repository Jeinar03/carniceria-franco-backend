<?php

namespace App\Support;

use App\Models\Customers;
use App\Models\Sale;
use App\Rules\UbicacionGoogleMaps;
use Illuminate\Http\Request;

/**
 * Datos de entrega de un pedido hecho desde la tienda (venta por transferencia y Mercado Pago).
 *
 * Mandadito: el cliente debe aceptar el aviso de que el mandadito tiene costo aparte y
 * decir a donde se lleva el pedido. Recoger: no se pide nada, se ignoran esos campos.
 */
class DatosEntrega
{
    /** Reglas de validacion segun la forma de entrega que llega en la peticion. */
    public static function reglas(?string $tipoEntrega): array
    {
        $reglas = [
            'tipo_entrega' => 'nullable|in:' . implode(',', Sale::TIPOS_ENTREGA), // Sin el campo: mandadito
        ];

        if (($tipoEntrega ?: Sale::ENTREGA_MANDADITO) !== Sale::ENTREGA_MANDADITO) {
            return $reglas;
        }

        return $reglas + [
            'acepta_aviso_mandadito' => 'accepted',
            'direccion_entrega' => 'required|string|max:255',
            'referencias_entrega' => 'nullable|string|max:255',
            'ubicacion_url' => ['nullable', 'string', new UbicacionGoogleMaps()],
            'guardar_como_mi_direccion' => 'nullable|boolean',
        ];
    }

    public static function mensajes(): array
    {
        return [
            'acepta_aviso_mandadito.accepted' => 'Debes aceptar que el mandadito tiene un costo aparte para continuar.',
            'direccion_entrega.required' => 'Indica a dónde te llevamos el pedido.',
        ];
    }

    /** Columnas de `sales` que salen de la peticion ya validada. */
    public static function paraVenta(Request $request): array
    {
        if (($request->input('tipo_entrega') ?: Sale::ENTREGA_MANDADITO) !== Sale::ENTREGA_MANDADITO) {
            return [];
        }

        return [
            'direccion_entrega' => trim((string) $request->input('direccion_entrega')),
            'referencias_entrega' => self::limpio($request->input('referencias_entrega')),
            'ubicacion_url' => self::limpio($request->input('ubicacion_url')),
            'aviso_mandadito_aceptado_at' => now(),
        ];
    }

    /** Si el cliente lo pidio, esa direccion pasa a ser la guardada en su perfil. */
    public static function guardarEnCliente(Request $request, Customers $cliente): void
    {
        if (! $request->boolean('guardar_como_mi_direccion')
            || ($request->input('tipo_entrega') ?: Sale::ENTREGA_MANDADITO) !== Sale::ENTREGA_MANDADITO) {
            return;
        }

        $cliente->update([
            'direccion' => trim((string) $request->input('direccion_entrega')),
            'referencias' => self::limpio($request->input('referencias_entrega')),
            'ubicacion_url' => self::limpio($request->input('ubicacion_url')),
        ]);
    }

    private static function limpio($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
