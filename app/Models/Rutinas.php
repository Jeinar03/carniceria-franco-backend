<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rutinas extends Model
{

    use HasFactory;
    protected $table = 'rutinas';
    protected $fillable = [
        'nombre',
        'imagen',
        'descripcion',
        'tipo',
        'puntaje',
        'estado',
        'nivel',
        'repeticiones',
        'sexo'
    ];
    public function ejercicios(): HasMany
    {
        return $this->hasMany(Ejercicios::class, 'rutina_id'); // Ajusta el nombre de la clave foránea según corresponda en tu base de datos
    }
    public function customers()
    {
        return $this->belongsToMany(Customers::class, 'rutinas_clientes', 'rutinas_id', 'customers_id');
    }
    public function videos()
    {
        return $this->belongsToMany(VideoGM::class, 'rutinas_ejercicios', 'rutinas_id', 'videos_gm_id')
            ->withPivot('dia'); // Incluimos el campo 'dia' de la tabla pivote
    }
}
