<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Logs extends Model
{
    use HasFactory;
    protected $table = 'logs';

    // Define los campos asignables en masa
    protected $fillable = [
        'accion',
        'contenido',
        'usuario',
        'created_at',
        'updated_at',
    ];

    // Define la relación con el modelo Customer
    public function customer()
    {
        return $this->belongsTo(Customers::class, 'usuario', 'id');
    }

}
