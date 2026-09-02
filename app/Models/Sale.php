<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    protected $fillable = [
        'customer_id',
        'folio',
        'fecha_venta',
        'subtotal',
        'descuento',
        'impuestos',
        'total',
        'metodo_pago',
        'mercadopago_payment_id',
        'mercadopago_status',
        'transferencia_estado',
        'transferencia_evidencia_path',
        'transferencia_subida_at',
        'transferencia_validada_at',
        'transferencia_validada_por',
        'estatus',
        'notas',
        'usuario_id',
        'estado_envio'
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total' => 'decimal:2',
        'transferencia_subida_at' => 'datetime',
        'transferencia_validada_at' => 'datetime',
    ];

    protected $attributes = [
        'estatus' => 'completada',
        'metodo_pago' => 'efectivo',
        'subtotal' => 0,
        'descuento' => 0,
        'impuestos' => 0,
        'transferencia_estado' => null,
    ];

    protected $appends = [
        'transferencia_evidencia_url',
    ];

    // Relación: Una venta pertenece a un cliente
    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    // Relación: Una venta tiene muchos detalles
    public function details()
    {
        return $this->hasMany(SaleDetail::class, 'sale_id');
    }

    // Relación: Una venta puede ser registrada por un usuario
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function indicadorRespuestas()
    {
        return $this->hasMany(IndicadorRespuesta::class, 'sale_id');
    }

    // Scope para ventas completadas
    public function scopeCompletadas($query)
    {
        return $query->where('estatus', 'completada');
    }

    // Scope para ventas pendientes
    public function scopePendientes($query)
    {
        return $query->where('estatus', 'pendiente');
    }

    // Scope para ventas canceladas
    public function scopeCanceladas($query)
    {
        return $query->where('estatus', 'cancelada');
    }

    // Scope para ventas por rango de fechas
    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_venta', [$fechaInicio, $fechaFin]);
    }

    // Scope para ventas por cliente
    public function scopePorCliente($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // Scope para ventas por método de pago
    public function scopePorMetodoPago($query, $metodo)
    {
        return $query->where('metodo_pago', $metodo);
    }

    // Accessor para calcular el número de items
    public function getItemsCountAttribute()
    {
        return $this->details()->sum('cantidad');
    }

    public function getTransferenciaEvidenciaUrlAttribute()
    {
        if (!$this->transferencia_evidencia_path) {
            return null;
        }

        return route('api.ventas.transferencia.evidencia.show', ['saleId' => $this->id]);
    }

    // Método estático para generar folio único
    public static function generarFolio()
    {
        $fecha = now()->format('Ymd');
        $ultimo = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $consecutivo = $ultimo ? intval(substr($ultimo->folio, -4)) + 1 : 1;

        return $fecha . '-' . str_pad($consecutivo, 4, '0', STR_PAD_LEFT);
    }

    // Boot method para generar folio automáticamente
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (!$sale->folio) {
                $sale->folio = self::generarFolio();
            }
        });
    }
}
