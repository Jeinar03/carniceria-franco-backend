<?php

namespace App\Http\Controllers;

use App\Models\IndicadorPregunta;
use App\Models\IndicadorRespuesta;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IndicadoresApiController extends Controller
{
    public function preguntasPedido(Request $request, int $saleId): JsonResponse
    {
        // El cuestionario siempre es del cliente autenticado.
        $request->merge(['customer_id' => $request->user()->id]);

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Error de validacion',
                'data' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::find($saleId);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Pedido no encontrado',
                'data' => null,
            ], 404);
        }

        if ((int) $sale->customer_id !== (int) $request->customer_id) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'El pedido no pertenece al cliente indicado',
                'data' => null,
            ], 403);
        }

        if (!$this->pedidoFinalizado($sale)) {
            return response()->json([
                'success' => false,
                'status' => 409,
                'message' => 'El cuestionario solo esta disponible para pedidos finalizados o entregados',
                'data' => null,
            ], 409);
        }

        $preguntas = IndicadorPregunta::activas()
            ->paraPedidoFinalizado()
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'pregunta', 'descripcion', 'orden']);

        $respondidas = IndicadorRespuesta::where('sale_id', $sale->id)
            ->where('customer_id', $request->customer_id)
            ->pluck('pregunta_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->values();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Preguntas obtenidas correctamente',
            'data' => [
                'sale_id' => $sale->id,
                'customer_id' => (int) $request->customer_id,
                'escala' => [
                    'min' => 1,
                    'max' => 10,
                ],
                'ya_respondio' => $respondidas->count() > 0,
                'preguntas_respondidas' => $respondidas,
                'preguntas' => $preguntas,
            ],
        ], 200);
    }

    public function guardarRespuestas(Request $request, int $saleId): JsonResponse
    {
        // El cuestionario siempre es del cliente autenticado.
        $request->merge(['customer_id' => $request->user()->id]);

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'respuestas' => 'required|array|min:1',
            'respuestas.*.pregunta_id' => 'required|exists:indicador_preguntas,id',
            'respuestas.*.respuesta' => 'required|integer|min:1|max:10',
            'respuestas.*.comentario' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Error de validacion',
                'data' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::find($saleId);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Pedido no encontrado',
                'data' => null,
            ], 404);
        }

        if ((int) $sale->customer_id !== (int) $request->customer_id) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'El pedido no pertenece al cliente indicado',
                'data' => null,
            ], 403);
        }

        if (!$this->pedidoFinalizado($sale)) {
            return response()->json([
                'success' => false,
                'status' => 409,
                'message' => 'El cuestionario solo puede responderse cuando el pedido ya fue finalizado o entregado',
                'data' => null,
            ], 409);
        }

        $preguntasValidas = IndicadorPregunta::activas()
            ->paraPedidoFinalizado()
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        foreach ($request->respuestas as $respuesta) {
            if (!in_array((int) $respuesta['pregunta_id'], $preguntasValidas, true)) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Una o mas preguntas no estan activas para pedidos finalizados',
                    'data' => null,
                ], 422);
            }
        }

        try {
            $guardadas = DB::transaction(function () use ($request, $sale) {
                $result = [];

                foreach ($request->respuestas as $respuesta) {
                    $result[] = IndicadorRespuesta::updateOrCreate(
                        [
                            'pregunta_id' => $respuesta['pregunta_id'],
                            'sale_id' => $sale->id,
                            'customer_id' => $request->customer_id,
                        ],
                        [
                            'respuesta' => (int) $respuesta['respuesta'],
                            'comentario' => $respuesta['comentario'] ?? null,
                        ]
                    );
                }

                return $result;
            });

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Respuestas guardadas correctamente',
                'data' => [
                    'sale_id' => $sale->id,
                    'customer_id' => (int) $request->customer_id,
                    'total_respuestas' => count($guardadas),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al guardar respuestas: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    private function pedidoFinalizado(Sale $sale): bool
    {
        $estadoEnvio = strtolower((string) $sale->estado_envio);
        $estatus = strtolower((string) $sale->estatus);

        return in_array($estadoEnvio, ['enviado', 'entregado', 'entregada'], true)
            || in_array($estatus, ['entregado', 'entregada'], true);
    }
}
