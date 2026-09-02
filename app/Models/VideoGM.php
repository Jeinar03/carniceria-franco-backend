<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoGM extends Model
{
    use HasFactory;
    protected $table = 'videos_gm';
    public function grupoMuscular()
    {
        return $this->belongsTo(GrupoMuscular::class, 'gm_id');
    }

    public function ejercicios()
    {
        return $this->belongsToMany(Ejercicios::class, 'videos_ejercicios', 'videos_gm_id', 'ejercicios_id');
    }

    public function equipos()
    {
        return $this->belongsToMany(Equipo::class, 'equipo_videos', 'videos_gm_id', 'equipo_id');
    }

    public function mecanicas()
    {
        return $this->belongsToMany(Mecanica::class, 'videos_mecanicas', 'videos_gm_id', 'mecanicas_id');
    }

    public function tiposEjercicio()
    {
        return $this->belongsToMany(TipoEjercicio::class, 'videos_tipo_ejercicio', 'videos_gm_id', 'tipo_ejercicio_id');
    }
}
