<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    use HasFactory;
    protected $table = 'equipo';
    protected $fillable = [
        'id',
        'nombre'
    ];

    public function videos()
    {
        return $this->belongsToMany(VideoGM::class, 'equipo_videos', 'equipo_id', 'videos_gm_id');
    }
    public function gruposMusculares()
    {
        return $this->belongsToMany(GruposMusculares::class, 'equipo_grupos_musculares', 'equipo_id', 'grupos_musculares_id');
    }
}
