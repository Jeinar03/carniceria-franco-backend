<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Respuesta extends Model
{
    use HasFactory;
    protected $table = 'respuestas';
    protected $fillable = [
        'respuestas_opciones_id', 'preguntas_id', 'customers_id'
    ];

    public function respuestaOpcion()
    {
        return $this->belongsTo(RespuestaOpcion::class, 'respuestas_opciones_id');
    }

    public function pregunta()
    {
        return $this->belongsTo(Pregunta::class, 'preguntas_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customers_id');
    }
}
