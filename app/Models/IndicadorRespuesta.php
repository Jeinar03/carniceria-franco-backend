<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndicadorRespuesta extends Model
{
    use HasFactory;

    protected $table = 'indicador_respuestas';

    protected $fillable = [
        'pregunta_id',
        'sale_id',
        'customer_id',
        'respuesta',
        'comentario',
    ];

    protected $casts = [
        'respuesta' => 'integer',
    ];

    public function pregunta()
    {
        return $this->belongsTo(IndicadorPregunta::class, 'pregunta_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }
}
