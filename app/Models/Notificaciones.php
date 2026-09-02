<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificaciones extends Model
{
    use HasFactory;
    protected $table = 'notificaciones';
    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha_envio',
        'logo',
        'titulo_notificacion'
    ];
}
