<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\InventoryMovement;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'codigo',
        'nombre',
        'descripcion',
        'precio',
        'precio_oferta',
        'en_oferta',
        'unidad_venta',
        'stock',
        'stock_minimo',
        'imagen',
        'imagenes',
        'peso_promedio',
        'activo',
        'destacado',
        'refrigerado',
        'fecha_vencimiento',
        'etiquetas',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_oferta' => 'decimal:2',
        'stock' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
        'peso_promedio' => 'decimal:2',
        'en_oferta' => 'boolean',
        'activo' => 'boolean',
        'destacado' => 'boolean',
        'refrigerado' => 'boolean',
        'fecha_vencimiento' => 'date',
        'imagenes' => 'array', // Para manejar JSON
    ];

    protected $attributes = [
        'en_oferta' => false,
        'activo' => true,
        'destacado' => false,
        'refrigerado' => true,
        'stock' => 0,
        'stock_minimo' => 0,
        'unidad_venta' => 'kilogramo',
    ];

    // Atributos que se incluyen automáticamente en las respuestas JSON
    protected $appends = [
        'imagen_url',
        'stock',
    ];

    protected $hidden = [
        'inventory_stock',
    ];

    // Relación: Un producto pertenece a una categoría
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getStockAttribute($value)
    {
        if (array_key_exists('inventory_stock', $this->attributes)) {
            return round((float) $this->attributes['inventory_stock'], 3);
        }

        if ($this->relationLoaded('inventoryMovements')) {
            return round((float) $this->inventoryMovements->sum(function ($movement) {
                if ($movement->status === InventoryMovement::STATUS_CANCELLED) {
                    return 0;
                }

                return $movement->type === InventoryMovement::TYPE_ENTRY
                    ? (float) $movement->quantity
                    : -(float) $movement->quantity;
            }), 3);
        }

        $balance = $this->inventoryMovements()
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 0 WHEN type = 'entrada' THEN quantity ELSE -quantity END), 0) AS stock")
            ->first();

        return round((float) $balance->stock, 3);
    }

    // Accessor para obtener el precio final (con oferta o normal)
    public function getPrecioFinalAttribute()
    {
        return $this->en_oferta && $this->precio_oferta ? $this->precio_oferta : $this->precio;
    }

    // Accessor para obtener la URL completa de la imagen
    public function getImagenUrlAttribute()
    {
        $defaultImageUrl = url('/productos/carne_default.png');

        if (!$this->imagen) {
            return $defaultImageUrl;
        }

        $image = trim((string) $this->imagen);

        if (str_starts_with($image, 'data:image')) {
            return $image;
        }

        // Si viene apuntando a localhost/127, forzar imagen por defecto.
        if (str_contains($image, '127.0.0.1') || str_contains($image, 'localhost')) {
            return $defaultImageUrl;
        }

        // Si ya es una URL remota valida (que no sea localhost), se respeta.
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        // Resolver ruta local relativa dentro de /public o /storage.
        $relativePath = ltrim($image, '/');

        if (str_starts_with($relativePath, 'storage/')) {
            $storagePath = Str::after($relativePath, 'storage/');

            if (Storage::disk('public')->exists($storagePath)) {
                return Storage::url($storagePath);
            }

            if (file_exists(public_path($relativePath))) {
                return url('/' . $relativePath);
            }
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::url($relativePath);
        }

        if (!str_starts_with($relativePath, 'productos/')) {
            $relativePath = 'productos/' . $relativePath;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::url($relativePath);
        }

        if (file_exists(public_path($relativePath))) {
            return url('/' . $relativePath);
        }

        return $defaultImageUrl;
    }

    // Accessor para verificar si hay stock disponible
    public function getTieneStockAttribute()
    {
        return $this->stock > 0;
    }

    // Accessor para verificar si está bajo en stock
    public function getStockBajoAttribute()
    {
        return $this->stock <= $this->stock_minimo;
    }

    // Scope para productos activos
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    // Scope para productos en oferta
    public function scopeEnOferta($query)
    {
        return $query->where('en_oferta', true);
    }

    // Scope para productos destacados
    public function scopeDestacados($query)
    {
        return $query->where('destacado', true);
    }

    // Scope para productos con stock disponible
    public function scopeConStock($query)
    {
        return $query->whereRaw("COALESCE((SELECT SUM(CASE WHEN im.status = 'cancelled' THEN 0 WHEN im.type = 'entrada' THEN im.quantity ELSE -im.quantity END) FROM inventory_movements im WHERE im.product_id = products.id), 0) > 0");
    }

    public function scopeSinStock($query)
    {
        return $query->whereRaw("COALESCE((SELECT SUM(CASE WHEN im.status = 'cancelled' THEN 0 WHEN im.type = 'entrada' THEN im.quantity ELSE -im.quantity END) FROM inventory_movements im WHERE im.product_id = products.id), 0) <= 0");
    }

    public function scopeWithInventoryStock($query)
    {
        return $query->select('products.*')->selectSub(function ($subQuery) {
            $subQuery->from('inventory_movements')
                ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 0 WHEN type = 'entrada' THEN quantity ELSE -quantity END), 0)")
                ->whereColumn('inventory_movements.product_id', 'products.id');
        }, 'inventory_stock');
    }

    // Scope para productos con stock bajo
    public function scopeStockBajo($query)
    {
        return $query->whereRaw("COALESCE((SELECT SUM(CASE WHEN im.status = 'cancelled' THEN 0 WHEN im.type = 'entrada' THEN im.quantity ELSE -im.quantity END) FROM inventory_movements im WHERE im.product_id = products.id), 0) <= products.stock_minimo");
    }

    // Scope para buscar por nombre o código
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('nombre', 'like', "%{$term}%")
              ->orWhere('codigo', 'like', "%{$term}%")
              ->orWhere('descripcion', 'like', "%{$term}%");
        });
    }

    // Scope para filtrar por categoría
    public function scopePorCategoria($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
