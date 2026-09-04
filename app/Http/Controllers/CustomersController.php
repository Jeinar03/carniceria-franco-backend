<?php

namespace App\Http\Controllers;

use App\Models\Ejercicios;
use App\Models\RespuestaOpcion;
use App\Models\Rutinas;
use App\Models\SeguimientoClientesImagenes;
use Illuminate\Http\Request;
use App\Models\Customers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CustomersController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'apellido' => 'required|string',
            'apellido2' => 'nullable|string',
            'correo' => 'required|email|unique:customers',
            'password' => 'required|string|min:6',
            'telefono' => 'nullable|string|min:10',
            'direccion' => 'nullable|string',
            'ciudad' => 'nullable|string',
            'estado' => 'nullable|string',
            'codigo_postal' => 'nullable|string',
            'rfc' => 'nullable|string',
            'tipo_cliente' => 'nullable|in:minorista,mayorista,distribuidor',
            'limite_credito' => 'nullable|numeric|min:0',
            'descuento_preferencial' => 'nullable|numeric|min:0|max:100',
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
            $usuario = Customers::create([
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'apellido2' => $request->apellido2,
                'correo' => $request->correo,
                'password' => Hash::make($request->password),
                'telefono' => $request->telefono,
                'direccion' => $request->direccion,
                'ciudad' => $request->ciudad,
                'estado' => $request->estado,
                'codigo_postal' => $request->codigo_postal,
                'pais' => $request->pais ?? 'México',
                'rfc' => $request->rfc,
                'tipo_cliente' => $request->tipo_cliente ?? 'minorista',
                'estatus' => $request->estatus ?? 'activo',
                'limite_credito' => $request->limite_credito ?? 0,
                'descuento_preferencial' => $request->descuento_preferencial ?? 0,
                'notas' => $request->notas,
            ]);

            // Auto-login: emitir token Sanctum tras el registro
            $token = $usuario->createToken('tienda', ['cliente'])->plainTextToken;

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Usuario creado correctamente',
                'data' => [
                    'token' => $token,
                    'cliente' => $usuario,
                ]
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            // Error de la base de datos
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error en la base de datos al crear usuario: ' . $e->getMessage(),
                'data' => null
            ]);
        } catch (\Exception $e) {
            // Otro tipo de error
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al crear usuario: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Error de validación',
                'data' => $validator->errors()
            ], 422);
        }
        //122344 =dkjfhjghkdhfghdgeruihg == dkjfhjghkdhfghdgeruihg
        try {
            // Buscar al cliente por su correo electrónico
            $cliente = Customers::where('correo', $request->correo)->first();
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'Credenciales inválidas',
                    'data' => null
                ], 401);
            }

            // Verificar si la contraseña es incorrecta
            if (!Hash::check($request->password, $cliente->password)) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'Credenciales inválidas',
                    'data' => null
                ], 401);
            }

            // Verificar si el cliente está inactivo
            if ($cliente->estatus != 'activo') {
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'Cuenta inactiva o suspendida. Contacte con el soporte.',
                    'data' => null
                ], 403);
            }


            // Credenciales válidas: emitir token Sanctum (7 días, ability 'cliente')
            $token = $cliente->createToken('tienda', ['cliente'])->plainTextToken;

            // Devolver la respuesta con el token y los datos del cliente
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Inicio de sesión exitoso',
                'data' => [
                    'token' => $token,
                    'cliente' => $cliente
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al iniciar sesión: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Cierra la sesión del cliente revocando el token con el que llegó la petición.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Sesión cerrada',
            'data' => null
        ], 200);
    }

    public function getData(Request $request)
    {
        // El cliente autenticado por el token Sanctum. Se ignora cualquier
        // 'clienteId' que venga en la query: un cliente sólo ve sus propios datos.
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Información del cliente obtenida correctamente',
            'data' => $request->user()
        ], 200);
    }
    public function update(Request $request, $id)
    {
        // Un cliente sólo puede modificar su propia cuenta
        if ((int) $id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'No autorizado',
                'data' => null
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string',
            'apellido' => 'sometimes|required|string',
            'apellido2' => 'nullable|string',
            'correo' => 'sometimes|required|email|unique:customers,correo,' . $id,
            'telefono' => 'nullable|string|min:10',
            'direccion' => 'nullable|string',
            'ciudad' => 'nullable|string',
            'estado' => 'nullable|string',
            'codigo_postal' => 'nullable|string',
            'pais' => 'nullable|string',
            'rfc' => 'nullable|string',
            'tipo_cliente' => 'nullable|in:minorista,mayorista,distribuidor',
            'limite_credito' => 'nullable|numeric|min:0',
            'descuento_preferencial' => 'nullable|numeric|min:0|max:100',
            'notas' => 'nullable|string',
            'password' => 'nullable|string|min:6',
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
            $cliente = Customers::find($id);

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Cliente no encontrado',
                    'data' => null
                ], 404);
            }

            $allowedFields = [
                'nombre',
                'apellido',
                'apellido2',
                'correo',
                'telefono',
                'direccion',
                'ciudad',
                'estado',
                'codigo_postal',
                'pais',
                'rfc',
                'tipo_cliente',
                'limite_credito',
                'descuento_preferencial',
                'notas',
            ];

            $changes = [];

            foreach ($allowedFields as $field) {
                if ($request->exists($field) && $cliente->{$field} != $request->input($field)) {
                    $changes[$field] = $request->input($field);
                }
            }

            if ($request->filled('password')) {
                $changes['password'] = Hash::make($request->input('password'));
            }

            if (!empty($changes)) {
                $cliente->fill($changes);
                $cliente->save();
            }


            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Cliente actualizado correctamente',
                'data' => [
                    'cliente' => $cliente->fresh(),
                    'campos_actualizados' => array_keys($changes)
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al actualizar el cliente: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function storeImages(Request $request)
    {
        // Validación del request
        $validator = Validator::make($request->all(), [
            'images' => 'required|array',                   // Asegura que 'images' sea un array
            'images.*.image' => 'required|string',          // Cada imagen debe ser una cadena en Base64
            'images.*.peso' => 'nullable|numeric',           // El peso es opcional y debe ser una cadena
            'images.*.comentarios' => 'nullable|string'     // Los comentarios son opcionales y deben ser una cadena
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
            // Las imágenes siempre se asocian al cliente autenticado
            $customerId = $request->user()->id;
            $images = $request->input('images');

            // Guardar cada imagen en la tabla seguimiento_clientes_imagenes
            foreach ($images as $imageData) {
                $decodedImage = base64_decode($imageData['image'], true);
                if ($decodedImage === false) {
                    //throw new \Exception('La imagen no es válida en formato Base64');
                    return response()->json([
                        'success' => false,
                        'status' => 500,
                        'message' => 'La imagen no es válida en formato Base64',
                        'data' => null
                    ], 500);
                }
                $imagen = new SeguimientoClientesImagenes();
                $imagen->customers_id = $customerId;
              $imagen->image = $imageData['image']; // Decodificar Base64 a binario

                // Guardar los campos opcionales
                $imagen->peso = $imageData['peso'] ?? null;
                $imagen->comentarios = $imageData['comentarios'] ?? null;
                $imagen->save();
            }

            // Excluir datos binarios de la respuesta
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Imágenes guardadas correctamente',
                'data' => null // Asegurarse de que no se incluyen datos binarios en la respuesta
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al guardar imágenes: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function listImages(Request $request, $customerId)
    {
        try {
            // Un cliente sólo puede ver sus propias imágenes
            if ((int) $customerId !== (int) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'No autorizado',
                    'data' => null
                ], 403);
            }

            // Verificar si el cliente existe
            $cliente = Customers::find($customerId);
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Cliente no encontrado',
                    'data' => null
                ], 404);
            }

            // Obtener las imágenes asociadas al cliente
            $imagenes = SeguimientoClientesImagenes::where('customers_id', $customerId)->get();

            // Convertir las imágenes a formato Base64 e incluir peso y comentarios
            $imagenesBase64 = $imagenes->map(function ($imagen) {
                return [
                    'id' => $imagen->id,
                    'image' =>$imagen->image, // Codificar la imagen en Base64
                    'peso' => $imagen->peso,                  // Incluir el peso
                    'comentarios' => $imagen->comentarios,
                    'created_at' => $imagen->created_at      // Incluir los comentarios
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Imágenes obtenidas correctamente',
                'data' => $imagenesBase64
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener las imágenes: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function deleteImage(Request $request, $customerId, $imageId)
    {
        try {
            // Un cliente sólo puede borrar sus propias imágenes
            if ((int) $customerId !== (int) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'No autorizado',
                    'data' => null
                ], 403);
            }

            // Verificar si el cliente existe
            $cliente = Customers::find($customerId);
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Cliente no encontrado',
                    'data' => null
                ], 404);
            }

            // Buscar la imagen específica del cliente
            $imagen = SeguimientoClientesImagenes::where('customers_id', $customerId)
                ->where('id', $imageId)
                ->first();
            if (!$imagen) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Imagen no encontrada',
                    'data' => null
                ], 404);
            }

            // Eliminar la imagen
            $imagen->delete();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Imagen eliminada correctamente',
                'data' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al eliminar la imagen: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
