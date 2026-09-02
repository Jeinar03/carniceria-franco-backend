<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GruposMusculares extends Model
{
    use HasFactory;
    protected $table = 'grupos_musculares';
    protected $fillable = [
        'nombre',
        'imagen',
        'descripcion',


    ];
    public function videos(): HasMany
    {
        return $this->hasMany(GruposMuscularesVideos::class, 'gm_id'); // Ajusta el nombre de la clave foránea según corresponda en tu base de datos
    }
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tags_grupos_musculares', 'grupos_musculares_id', 'tags_id');
    }
    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'equipo_grupos_musculares', 'grupos_musculares_id', 'equipo_id');
    }
    public function equiposVideos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'equipo_videos', 'videos_gm_id', 'equipo_id');
    }
}
