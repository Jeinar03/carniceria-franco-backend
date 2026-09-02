<?php

namespace App\Http\Controllers;

use App\Models\SiteAlert;
use App\Models\SiteConfig;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class SitioApiController extends Controller
{
    /**
     * Devuelve la configuración del sitio activa.
     *
     * GET /api/v1/sitio/config
     */
    public function getConfig(): JsonResponse
    {
        try {
            $config = SiteConfig::where('activo', true)->first();

            if (! $config) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'No hay una configuración activa del sitio.',
                    'data'    => null,
                ], 404);
            }

            $data                = $config->toArray();
            $data['logo_url']    = $config->logo_url;

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Configuración del sitio obtenida correctamente.',
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Error al obtener la configuración.',
            ], 500);
        }
    }

    /**
     * Devuelve las alertas emergentes activas y vigentes.
     *
     * Una alerta se considera vigente cuando:
     *  - está activa (activo = 1)
     *  - no tiene fecha_inicio   → siempre vigente mientras esté activa
     *  - tiene fecha_inicio      → ahora >= fecha_inicio  Y  ahora <= fecha_inicio + dias_duracion
     *
     * GET /api/v1/sitio/alertas
     */
    public function getAlertas(): JsonResponse
    {
        try {
            $now = Carbon::now();

            $alertas = SiteAlert::where('activo', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('fecha_inicio')
                          ->orWhere(function ($q) use ($now) {
                              $q->where('fecha_inicio', '<=', $now)
                                ->whereRaw(
                                    'DATE_ADD(fecha_inicio, INTERVAL dias_duracion DAY) >= ?',
                                    [$now]
                                );
                          });
                })
                ->orderByDesc('created_at')
                ->get()
                ->map(function (SiteAlert $alerta) {
                    $item              = $alerta->toArray();
                    $item['imagen_url'] = $alerta->imagen_url;
                    $item['fecha_fin']  = $alerta->fecha_fin?->toISOString();
                    return $item;
                });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Alertas obtenidas correctamente.',
                'data'    => $alertas,
                'total'   => $alertas->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Error al obtener las alertas.',
            ], 500);
        }
    }
}
