<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;
    protected $fillable = [
        'nombre'
    ];

    public function gruposMusculares()
    {
        return $this->belongsToMany(GruposMusculares::class, 'tags_grupos_musculares', 'tags_id', 'grupos_musculares_id');
    }
}
