<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ejercicios extends Model
{
    use HasFactory;
    protected $table = 'ejercicios';
    protected $fillable = [
        'nombre',
        'imagen',
        'descripcion',
        'rutina_id',
        'status',
        'tipo'


    ];
    public function rutina()
    {
        return $this->belongsTo(Rutinas::class, 'rutina_id');
    }
    public function videos()
    {
        return $this->belongsToMany(VideoGM::class, 'videos_ejercicios', 'ejercicios_id', 'videos_gm_id');
    }
}
