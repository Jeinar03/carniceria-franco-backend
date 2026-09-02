<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage; // usado para delete en el controlador

class SiteAlert extends Model
{
    use HasFactory;

    protected $table = 'site_alerts';

    protected $fillable = [
        'titulo',
        'descripcion',
        'imagen',
        'link_url',
        'link_texto',
        'fecha_inicio',
        'dias_duracion',
        'tipo',
        'activo',
    ];

    protected $casts = [
        'fecha_inicio'  => 'datetime',
        'dias_duracion' => 'integer',
        'activo'        => 'boolean',
    ];

    /**
     * Fecha de finalización calculada.
     */
    public function getFechaFinAttribute(): ?Carbon
    {
        if (! $this->fecha_inicio) {
            return null;
        }

        return $this->fecha_inicio->copy()->addDays($this->dias_duracion);
    }

    /**
     * Indica si la alerta debe mostrarse ahora (activa y dentro del rango de fechas).
     */
    public function getEsVisibleAttribute(): bool
    {
        if (! $this->activo) {
            return false;
        }

        if (! $this->fecha_inicio) {
            return true; // Sin programación = siempre visible mientras esté activa
        }

        $now = Carbon::now();

        return $now->between($this->fecha_inicio, $this->fecha_fin);
    }

    /**
     * URL completa de la imagen.
     */
    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen ? asset('storage/' . $this->imagen) : null;
    }
}
