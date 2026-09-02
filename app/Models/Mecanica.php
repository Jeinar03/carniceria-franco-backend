<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mecanica extends Model
{
    use HasFactory;
    protected $fillable = [
        'nombre'
    ];

    public function videos()
    {
        return $this->belongsToMany(VideoGM::class, 'videos_mecanicas', 'mecanicas_id', 'videos_gm_id');
    }
}
