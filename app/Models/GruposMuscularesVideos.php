<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GruposMuscularesVideos extends Model
{
    use HasFactory;
    protected $table = 'videos_gm';
    protected $fillable = [
        'nombre',
        'miniatura',
        'descripcion',
        'gm_id',
        'video_url',
        'lesion'


    ];
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tags_videos_gm', 'videos_gm_id', 'tags_id');
    }
    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'equipo_videos', 'videos_gm_id', 'equipo_id');
    }
    public function grupoMuscular()
    {
        return $this->belongsTo(GruposMusculares::class, 'gm_id');
    }
}
