<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'nombre',
        'descripcion',
        'imagen',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
        'orden' => 0,
    ];

    // Relación: Una categoría tiene muchos productos
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // Scope para obtener solo categorías activas
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    // Scope para ordenar por campo 'orden'
    public function scopeOrdered($query)
    {
        return $query->orderBy('orden', 'asc');
    }
}
