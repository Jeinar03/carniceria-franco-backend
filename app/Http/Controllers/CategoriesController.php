<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoriesController extends Controller
{
    /**
     * Obtener todas las categorías activas
     */
    public function index()
    {
        try {
            $categories = Category::active()
                ->ordered()
                ->withCount('products')
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Categorías obtenidas correctamente',
                'data' => $categories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener categorías: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener todas las categorías (incluyendo inactivas)
     */
    public function all()
    {
        try {
            $categories = Category::ordered()
                ->withCount('products')
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Todas las categorías obtenidas correctamente',
                'data' => $categories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener categorías: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener detalle de una categoría
     */
    public function show($id)
    {
        try {
            $category = Category::with(['products' => function($query) {
                $query->withInventoryStock()->active()->conStock();
            }])->find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Categoría no encontrada',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Categoría obtenida correctamente',
                'data' => $category
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener la categoría: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener productos de una categoría
     */
    public function getProducts($id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Categoría no encontrada',
                    'data' => null
                ], 404);
            }

            $products = $category->products()
                ->withInventoryStock()
                ->active()
                ->conStock()
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Productos obtenidos correctamente',
                'data' => [
                    'category' => $category,
                    'products' => $products
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener productos: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Crear una nueva categoría
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:3|unique:categories',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
            'orden' => 'integer|min:0',
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
            $category = Category::create($request->all());

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Categoría creada correctamente',
                'data' => $category
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al crear categoría: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Actualizar una categoría
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:3|unique:categories,nombre,' . $id,
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
            'orden' => 'integer|min:0',
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
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Categoría no encontrada',
                    'data' => null
                ], 404);
            }

            $category->update($request->all());

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Categoría actualizada correctamente',
                'data' => $category
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al actualizar categoría: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
