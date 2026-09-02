<?php

namespace App\Http\Controllers;

use App\Models\Logs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LogController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'accion' => 'required|string',
            'contenido' => 'required|string',
            'usuario' => 'required|numeric', // Aseguramos que sea un número
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Error de validación',
                'data' => $validator->errors()
            ], 422);
        }

        try {
            // Convertir el campo usuario a entero
            $usuarioData = $request->all();
            $usuarioData['usuario'] = (int) $usuarioData['usuario']; // Conversión a entero

            $usuario = Logs::create($usuarioData);
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Log guardado correctamente',
                'data' => $usuario
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            // Error de la base de datos
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error en la base de datos al guardar el log: ' . $e->getMessage(),
                'data' => null
            ]);
        } catch (\Exception $e) {
            // Otro tipo de error
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al guardar el log: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

}
