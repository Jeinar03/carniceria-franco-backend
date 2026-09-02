<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoClientesImagenes extends Model
{
    use HasFactory;
    protected $table = 'seguimiento_clientes_imagenes';

    protected $fillable = [
        'image',
        'customers_id',
        'peso',
        'comentarios'
    ];

    // Relación con el modelo Customer
    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customers_id');
    }
}
