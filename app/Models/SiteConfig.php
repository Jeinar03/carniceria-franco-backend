<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage; // usado para delete en el controlador

class SiteConfig extends Model
{
    use HasFactory;

    protected $table = 'site_configs';

    protected $fillable = [
        'nombre',
        'logo',
        'direccion',
        'correo',
        'telefono',
        'facebook_url',
        'instagram_url',
        'whatsapp',
        'banco',
        'titular_cuenta',
        'numero_cuenta',
        'clabe',
        'horarios',
        'activo',
    ];

    protected $casts = [
        'horarios' => 'array',
        'activo'   => 'boolean',
    ];

    /**
     * Datos bancarios capturados (solo los que no estan vacios), o null si no hay ninguno.
     * Se usan para mostrar al cliente a donde transferir.
     */
    public function datosBancarios(): ?array
    {
        $datos = array_filter([
            'banco' => $this->banco,
            'titular' => $this->titular_cuenta,
            'cuenta' => $this->numero_cuenta,
            'clabe' => $this->clabe,
        ], fn ($valor) => $valor !== null && $valor !== '');

        return $datos ?: null;
    }

    /**
     * URL completa del logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}
