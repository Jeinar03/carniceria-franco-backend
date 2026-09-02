<?php
namespace App\Http\Livewire\Productos;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\TemporaryUploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class ProductosController extends Component
{
    use WithPagination;
    use WithFileUploads;

    protected $listeners = [
        'deleteRow' => 'destroy',
        'resetUI'   => 'resetUI',
    ];

    public $pageTitle, $componentName;
    private $pagination = 10;
    public $category_id;
    public $codigo;
    public $nombre;
    public $descripcion;
    public $precio;
    public $precio_oferta;
    public $en_oferta    = 0;
    public $unidad_venta = 'kilogramo';
    public $stock_minimo = 0;
    public $imagen;
    public $peso_promedio;
    public $activo      = 1;
    public $destacado   = 0;
    public $etiquetas;
    public $selected_id = 0;
    public $search;
    public $filterCategory = '';
                                   // Campos para venta por monto
    public $monto_venta    = null; // Monto en pesos que el cliente pide
    public $cantidad_venta = null; // Cantidad en gramos/kilos calculada
    public $venta_por_gramos = false;

    public function mount()
    {
        $this->pageTitle     = 'Listado';
        $this->componentName = 'Productos';
    }

    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterCategory()
    {
        $this->resetPage();
    }

    public function render()
    {
        $categories = Category::active()->ordered()->get();
        $term = trim((string) $this->search);

        $data = Product::query()
            ->withInventoryStock()
            ->with('category')
            ->when($term !== '', function ($query) use ($term) {
                return $query->search($term);
            })
            ->when($this->filterCategory, function ($query) {
                return $query->where('category_id', $this->filterCategory);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->pagination);

        return view('livewire.productos.productos-controller', [
            'data'       => $data,
            'categories' => $categories,
        ])
            ->extends('layouts.theme.app')
            ->section('content');
    }

    public function resetUI()
    {
        $this->category_id       = '';
        $this->codigo            = '';
        $this->nombre            = '';
        $this->descripcion       = '';
        $this->precio            = '';
        $this->precio_oferta     = '';
        $this->en_oferta         = 0;
        $this->unidad_venta      = 'kilogramo';
        $this->stock_minimo      = 0;
        $this->imagen            = null;
        $this->peso_promedio     = '';
        $this->activo            = 1;
        $this->destacado         = 0;
        $this->etiquetas         = '';
        $this->selected_id       = 0;
        $this->monto_venta       = null;
        $this->cantidad_venta    = null;
        $this->venta_por_gramos  = false;
        $this->resetValidation();
        $this->resetPage();
    }
    /**
     * Convierte un monto en pesos a gramos/kilos según el precio del producto.
     * Si el producto se vende por kilo, regresa los gramos equivalentes.
     * Si se vende por gramo, regresa los gramos equivalentes.
     * Si se vende por pieza, paquete, caja, litro, regresa null.
     */
    public function calcularCantidadPorMonto($monto, $precio, $unidad_venta)
    {
        $monto = (float) $monto;
        $precio = (float) $precio;

        if ($monto <= 0 || $precio <= 0) {
            return null;
        }

        if ($this->venta_por_gramos || in_array($unidad_venta, ['kilogramo', 'gramo'])) {
            $cantidad_kg = $monto / $precio; // cantidad en kilos
            if ($unidad_venta === 'kilogramo') {
                return round($cantidad_kg * 1000, 2); // gramos
            }

            if ($unidad_venta === 'gramo' || $this->venta_por_gramos) {
                return round($cantidad_kg * 1000, 2); // gramos
            }
        }
        // Para otras unidades no aplica
        return null;
    }

    /**
     * Evento Livewire para cuando el usuario ingresa el monto de venta
     * Calcula la cantidad equivalente y la asigna a $cantidad_venta
     */
    public function updatedMontoVenta($value)
    {
        $precio_final = $this->en_oferta && $this->precio_oferta ? $this->precio_oferta : $this->precio;
        $this->cantidad_venta = $this->calcularCantidadPorMonto($value, $precio_final, $this->unidad_venta);
    }

    public function updatedEnOferta($value)
    {
        if ((int) $value !== 1) {
            $this->precio_oferta = null;
        }
    }

    public function updatedUnidadVenta($value)
    {
        if ($value !== 'pieza') {
            $this->peso_promedio = null;
        }

        if ($value === 'pieza') {
            $this->venta_por_gramos = false;
        }
    }

    public function updatedVentaPorGramos($value)
    {
        if ((bool) $value) {
            $this->unidad_venta = 'kilogramo';
        }

        $precio_final = $this->en_oferta && $this->precio_oferta ? $this->precio_oferta : $this->precio;
        $this->cantidad_venta = $this->calcularCantidadPorMonto($this->monto_venta, $precio_final, $this->unidad_venta);
    }


    /**
     * Evento Livewire para cuando el usuario cambia la cantidad manualmente
     * Si cambia la cantidad, borra el monto para evitar confusión
     */
    public function updatedCantidadVenta($value)
    {
        if (! is_null($value)) {
            $this->monto_venta = null;
        }
    }
    public function edit(Product $product)
    {
        $this->selected_id       = $product->id;
        $this->category_id       = $product->category_id;
        $this->codigo            = $product->codigo;
        $this->nombre            = $product->nombre;
        $this->descripcion       = $product->descripcion;
        $this->precio            = $product->precio;
        $this->precio_oferta     = $product->precio_oferta;
        $this->en_oferta         = $product->en_oferta;
        $this->unidad_venta      = $product->unidad_venta;
        $this->stock_minimo      = $product->stock_minimo;
        $this->peso_promedio     = $product->peso_promedio;
        $this->activo            = $product->activo;
        $this->destacado         = $product->destacado;
        $this->etiquetas         = $product->etiquetas;
        $this->venta_por_gramos  = in_array($product->unidad_venta, ['kilogramo', 'gramo']);
        $this->emit('show-modal', 'open!');
    }

    public function Store()
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $payload = $this->productPayload();

            if ($this->hasNewImage()) {
                $payload['imagen'] = $this->storeImage();
            }

            Product::create($payload);

            $this->resetUI();
            $this->emit('product-added', 'Producto registrado');
        } catch (Throwable $e) {
            Log::error('Error al registrar producto', [
                'nombre' => $this->nombre,
                'codigo' => $this->codigo,
                'error' => $e->getMessage(),
            ]);

            $this->emit('product-error', $this->buildFailureMessage($e, 'registrar'));
        }
    }

    public function Update()
    {
        $this->validate($this->rules($this->selected_id), $this->messages());

        try {
            $product = Product::findOrFail($this->selected_id);
            $previousImage = $product->imagen;

            $payload = $this->productPayload();
            if ($this->hasNewImage()) {
                $payload['imagen'] = $this->storeImage();
            }

            $product->update($payload);

            if ($this->hasNewImage() && $previousImage) {
                $this->deleteImage($previousImage);
            }

            $this->resetUI();
            $this->emit('product-updated', 'Producto actualizado');
        } catch (Throwable $e) {
            Log::error('Error al actualizar producto', [
                'product_id' => $this->selected_id,
                'nombre' => $this->nombre,
                'error' => $e->getMessage(),
            ]);

            $this->emit('product-error', $this->buildFailureMessage($e, 'actualizar'));
        }
    }

    public function destroy(Product $product)
    {
        try {
            if ($product->imagen) {
                $this->deleteImage($product->imagen);
            }

            $product->delete();
            $this->resetUI();
            $this->emit('product-deleted', 'Producto eliminado con éxito');
        } catch (Throwable $e) {
            Log::error('Error al eliminar producto', [
                'product_id' => $product->id ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->emit('product-error', $this->buildFailureMessage($e, 'eliminar'));
        }
    }

    private function rules(int $ignoreId = 0): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'codigo' => ['nullable', 'string', 'max:50', Rule::unique('products', 'codigo')->ignore($ignoreId)],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'precio' => ['required', 'numeric', 'min:0'],
            'precio_oferta' => ['nullable', 'required_if:en_oferta,1', 'numeric', 'min:0'],
            'en_oferta' => ['required', 'boolean'],
            'unidad_venta' => ['required', Rule::in(['kilogramo', 'gramo', 'pieza', 'paquete', 'caja', 'litro', 'pesos'])],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
            'peso_promedio' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['required', 'boolean'],
            'destacado' => ['required', 'boolean'],
            'etiquetas' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'category_id.required' => 'Selecciona una categoría',
            'category_id.exists' => 'La categoría no existe',
            'nombre.required' => 'Ingresa el nombre del producto',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres',
            'codigo.unique' => 'Este código ya existe',
            'precio.required' => 'Ingresa el precio',
            'precio.numeric' => 'El precio debe ser numérico',
            'precio_oferta.numeric' => 'El precio de oferta debe ser numérico',
            'precio_oferta.required_if' => 'Ingresa el precio de oferta cuando el producto esté en oferta',
            'unidad_venta.required' => 'Selecciona la unidad de venta',
            'stock_minimo.required' => 'Ingresa el stock mínimo',
            'imagen.image' => 'El archivo debe ser una imagen válida',
            'imagen.mimes' => 'La imagen debe ser JPG, JPEG, PNG, GIF o WEBP',
            'imagen.max' => 'La imagen no puede superar 2MB',
        ];
    }

    private function productPayload(): array
    {
        return [
            'category_id' => (int) $this->category_id,
            'codigo' => $this->normalizeNullableText($this->codigo),
            'nombre' => trim((string) $this->nombre),
            'descripcion' => $this->normalizeNullableText($this->descripcion),
            'precio' => (float) $this->precio,
            'precio_oferta' => $this->normalizeNullableNumber($this->precio_oferta),
            'en_oferta' => (bool) $this->en_oferta,
            'unidad_venta' => $this->venta_por_gramos ? 'kilogramo' : $this->unidad_venta,
            'stock_minimo' => (float) $this->stock_minimo,
            'peso_promedio' => $this->normalizeNullableNumber($this->peso_promedio),
            'activo' => (bool) $this->activo,
            'destacado' => (bool) $this->destacado,
            'etiquetas' => $this->normalizeNullableText($this->etiquetas),
        ];
    }

    private function storeImage(): string
    {
        if (! $this->hasNewImage()) {
            throw new \RuntimeException('No hay imagen válida para almacenar.');
        }

        $fileName = Str::uuid() . '.' . strtolower($this->imagen->getClientOriginalExtension());
        $path = $this->imagen->storeAs('productos', $fileName, 'public');

        return $path;
    }

    private function hasNewImage(): bool
    {
        return $this->imagen instanceof TemporaryUploadedFile;
    }

    private function deleteImage(string $image): void
    {
        $normalized = ltrim($image, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }

        $paths = [];

        if (Str::contains($normalized, '/')) {
            $paths[] = $normalized;
        } else {
            $paths[] = 'productos/' . $normalized;
        }

        foreach (array_unique($paths) as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            $legacyPath = public_path($path);
            if (file_exists($legacyPath)) {
                @unlink($legacyPath);
            }
        }
    }

    private function normalizeNullableText($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeNullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function buildFailureMessage(Throwable $e, string $action): string
    {
        if ($e instanceof QueryException) {
            $errorCode = $e->errorInfo[1] ?? null;

            if ($errorCode === 1062) {
                return "No se pudo {$action} el producto: el código ya existe.";
            }

            return "No se pudo {$action} el producto por un conflicto en base de datos.";
        }

        $message = trim((string) $e->getMessage());
        if ($message === '') {
            return "No se pudo {$action} el producto.";
        }

        return "No se pudo {$action} el producto: {$message}";
    }
}
