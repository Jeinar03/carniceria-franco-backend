<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Precio especial en pesos que el administrador le fija a un cliente en un
 * producto concreto. Se aplica en las 3 rutas de venta (mostrador, pedido
 * online, checkout MercadoPago) vía App\Services\PricingService.
 */
class CustomerProductPrice extends Model
{
    use HasFactory;

    protected $table = 'customer_product_prices';

    protected $fillable = [
        'customer_id',
        'product_id',
        'precio_especial',
        'activo',
        'notas',
        'created_by',
    ];

    protected $casts = [
        'precio_especial' => 'decimal:2',
        'activo' => 'boolean',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
