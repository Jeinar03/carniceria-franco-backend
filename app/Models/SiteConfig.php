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
        'horarios',
        'activo',
    ];

    protected $casts = [
        'horarios' => 'array',
        'activo'   => 'boolean',
    ];

    /**
     * URL completa del logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}
