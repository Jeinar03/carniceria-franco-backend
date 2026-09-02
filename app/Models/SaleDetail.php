<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    use HasFactory;

    protected $table = 'sale_details';

    protected $fillable = [
        'sale_id',
        'product_id',
        'cantidad',
        'monto_pesos',
        'precio_unitario',
        'precio_oferta',
        'descuento',
        'subtotal',
        'total',
        'producto_nombre',
        'producto_codigo',
        'unidad_venta',
        'estado_despacho'
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'monto_pesos' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'precio_oferta' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $attributes = [
        'descuento' => 0,
    ];

    // Relación: Un detalle pertenece a una venta
    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    // Relación: Un detalle pertenece a un producto
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Accessor para calcular el precio final usado
    public function getPrecioFinalAttribute()
    {
        return $this->precio_oferta ?? $this->precio_unitario;
    }

    // Accessor para calcular el ahorro si hay oferta
    public function getAhorroAttribute()
    {
        if ($this->precio_oferta && $this->precio_oferta < $this->precio_unitario) {
            return ($this->precio_unitario - $this->precio_oferta) * $this->cantidad;
        }
        return 0;
    }

    // Boot method para calcular automáticamente subtotal y total
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($detail) {
            // Calcular subtotal (precio * cantidad)
            $precioFinal = $detail->precio_oferta ?? $detail->precio_unitario;
            $detail->subtotal = $precioFinal * $detail->cantidad;

            // Calcular total (subtotal - descuento)
            $detail->total = $detail->subtotal - ($detail->descuento ?? 0);
        });

        static::updating(function ($detail) {
            // Recalcular si cambia cantidad o precios
            $precioFinal = $detail->precio_oferta ?? $detail->precio_unitario;
            $detail->subtotal = $precioFinal * $detail->cantidad;
            $detail->total = $detail->subtotal - ($detail->descuento ?? 0);
        });
    }
}
