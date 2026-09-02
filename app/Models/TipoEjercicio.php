<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoEjercicio extends Model
{
    use HasFactory;
    protected $fillable = [
        'nombre'
    ];

    public function videos()
    {
        return $this->belongsToMany(VideoGM::class, 'videos_tipo_ejercicio', 'tipo_ejercicio_id', 'videos_gm_id');
    }
}
