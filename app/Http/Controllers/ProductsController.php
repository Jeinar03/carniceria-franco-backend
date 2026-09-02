<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductsController extends Controller
{
    /**
     * Obtener todos los productos activos con stock
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with('category')
                ->withInventoryStock()
                ->active()
                ->conStock();

            // Filtrar por categoría si se proporciona
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filtrar productos en oferta
            if ($request->has('en_oferta') && $request->en_oferta == 1) {
                $query->enOferta();
            }

            // Filtrar productos destacados
            if ($request->has('destacado') && $request->destacado == 1) {
                $query->destacados();
            }

            // Buscar por nombre o código
            if ($request->has('search')) {
                $query->search($request->search);
            }

            // Ordenar
            $orderBy = $request->get('order_by', 'created_at');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Productos obtenidos correctamente',
                'data' => $products
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
     * Obtener productos destacados
     */
    public function featured()
    {
        try {
            $products = Product::with('category')
                ->withInventoryStock()
                ->active()
                ->destacados()
                ->conStock()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Productos destacados obtenidos correctamente',
                'data' => $products
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener productos destacados: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener productos en oferta
     */
    public function offers()
    {
        try {
            $products = Product::with('category')
                ->withInventoryStock()
                ->active()
                ->enOferta()
                ->conStock()
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Productos en oferta obtenidos correctamente',
                'data' => $products
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener productos en oferta: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener detalle de un producto
     */
    public function show($id)
    {
        try {
            $product = Product::with('category')->withInventoryStock()->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Producto no encontrado',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Producto obtenido correctamente',
                'data' => $product
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener el producto: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Buscar productos por nombre o código
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
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
            $products = Product::with('category')
                ->withInventoryStock()
                ->active()
                ->search($request->input('query'))
                ->conStock()
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Búsqueda realizada correctamente',
                'data' => $products
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al buscar productos: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener productos por categoría
     */
    public function byCategory($categoryId)
    {
        try {
            $category = Category::find($categoryId);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Categoría no encontrada',
                    'data' => null
                ], 404);
            }

            $products = Product::with('category')
                ->withInventoryStock()
                ->active()
                ->porCategoria($categoryId)
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
     * Verificar disponibilidad de stock
     */
    public function checkStock($id)
    {
        try {
            $product = Product::withInventoryStock()->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Producto no encontrado',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Stock verificado',
                'data' => [
                    'product_id' => $product->id,
                    'nombre' => $product->nombre,
                    'stock' => $product->stock,
                    'stock_minimo' => $product->stock_minimo,
                    'tiene_stock' => $product->tiene_stock,
                    'stock_bajo' => $product->stock_bajo,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al verificar stock: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Crear un nuevo producto
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'nombre' => 'required|string|min:3',
            'codigo' => 'nullable|string|unique:products',
            'precio' => 'required|numeric|min:0',
            'unidad_venta' => 'required|in:kilogramo,gramo,pieza,paquete,caja,litro',
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
            $payload = $request->except('stock');
            $payload['stock'] = 0; // Compatibilidad temporal con la columna antigua.
            $product = Product::create($payload);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Producto creado correctamente',
                'data' => $product
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al crear producto: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Actualizar un producto
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'nombre' => 'required|string|min:3',
            'codigo' => 'nullable|string|unique:products,codigo,' . $id,
            'precio' => 'required|numeric|min:0',
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
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Producto no encontrado',
                    'data' => null
                ], 404);
            }

            $product->update($request->except('stock'));

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Producto actualizado correctamente',
                'data' => $product
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al actualizar producto: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
