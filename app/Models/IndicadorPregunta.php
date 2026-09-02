<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndicadorPregunta extends Model
{
    use HasFactory;

    protected $table = 'indicador_preguntas';

    protected $fillable = [
        'pregunta',
        'descripcion',
        'activo',
        'mostrar_al_finalizar_pedido',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'mostrar_al_finalizar_pedido' => 'boolean',
        'orden' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
        'mostrar_al_finalizar_pedido' => true,
        'orden' => 0,
    ];

    public function respuestas()
    {
        return $this->hasMany(IndicadorRespuesta::class, 'pregunta_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeParaPedidoFinalizado($query)
    {
        return $query->where('mostrar_al_finalizar_pedido', true);
    }
}
