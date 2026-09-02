<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RespuestaOpcion extends Model
{
    use HasFactory;
    protected $table = 'respuestas_opciones';
    protected $fillable = [
        'opcion', 'preguntas_id', 'valor'
    ];

    public function pregunta()
    {
        return $this->belongsTo(Pregunta::class, 'preguntas_id');
    }

    public function respuestas()
    {
        return $this->hasMany(Respuesta::class, 'respuestas_opciones_id');
    }
}
