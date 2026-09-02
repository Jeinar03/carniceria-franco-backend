<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customers extends Authenticatable
{
    use HasFactory;
    protected $table = 'customers';
    protected $fillable = [
        'nombre',
        'apellido',
        'apellido2',
        'correo',
        'password',
        'telefono',
        'direccion',
        'ciudad',
        'estado',
        'codigo_postal',
        'pais',
        'rfc',
        'fecha_registro',
        'fecha_ultima_compra',
        'total_compras',
        'numero_compras',
        'saldo_cuenta',
        'limite_credito',
        'descuento_preferencial',
        'tipo_cliente',
        'estatus',
        'notas'
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'fecha_ultima_compra' => 'datetime',
        'total_compras' => 'decimal:2',
        'numero_compras' => 'integer',
        'saldo_cuenta' => 'decimal:2',
        'limite_credito' => 'decimal:2',
        'descuento_preferencial' => 'decimal:2',
    ];

    protected $attributes = [
        'pais' => 'México',
        'tipo_cliente' => 'minorista',
        'estatus' => 'activo',
        'total_compras' => 0.00,
        'numero_compras' => 0,
        'saldo_cuenta' => 0.00,
        'limite_credito' => 0.00,
        'descuento_preferencial' => 0.00,
    ];

    protected $hidden = [
        'password',
    ];

    public function indicadorRespuestas()
    {
        return $this->hasMany(IndicadorRespuesta::class, 'customer_id');
    }
}
