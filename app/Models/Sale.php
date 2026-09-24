<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    // Estatus de pago/venta (columna `estatus`)
    const ESTATUS_COMPLETADA = 'completada';
    const ESTATUS_PENDIENTE = 'pendiente';
    const ESTATUS_CANCELADA = 'cancelada';

    // Estado de envío/despacho (columna `estado_envio`)
    const ENVIO_PENDIENTE = 'Pendiente';
    const ENVIO_PROCESANDO = 'Procesando';
    const ENVIO_LISTO = 'Listo_para_enviar';
    const ENVIO_ENVIADO = 'Enviado';
    const ENVIO_ENTREGADO = 'Entregado';

    protected $fillable = [
        'customer_id',
        'folio',
        'fecha_venta',
        'fecha_entrega',
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
        'estado_envio',
        'entregado_at',
        'entregado_por',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'fecha_entrega' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total' => 'decimal:2',
        'transferencia_subida_at' => 'datetime',
        'transferencia_validada_at' => 'datetime',
        'entregado_at' => 'datetime',
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

    // Relación: usuario que marcó la venta como entregada
    public function entregadoPor()
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    public function indicadorRespuestas()
    {
        return $this->hasMany(IndicadorRespuesta::class, 'sale_id');
    }

    // Scope para ventas completadas
    public function scopeCompletadas($query)
    {
        return $query->where('estatus', self::ESTATUS_COMPLETADA);
    }

    // Scope para ventas pendientes
    public function scopePendientes($query)
    {
        return $query->where('estatus', self::ESTATUS_PENDIENTE);
    }

    // Scope para ventas canceladas
    public function scopeCanceladas($query)
    {
        return $query->where('estatus', self::ESTATUS_CANCELADA);
    }

    // Scope para pedidos programados a una fecha de entrega futura (posterior a hoy)
    public function scopeProgramadas($query)
    {
        return $query->whereDate('fecha_entrega', '>', now()->toDateString());
    }

    // Scope para pedidos de entrega inmediata (sin fecha_entrega o ya vencida/hoy)
    public function scopeEntregaInmediata($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('fecha_entrega')
                ->orWhereDate('fecha_entrega', '<=', now()->toDateString());
        });
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

    /**
     * Marca la venta como entregada de inmediato: cierra todas sus líneas de
     * despacho y pone el estado de envío en "Entregado" sin pasar por la
     * cola de Despachos. Pensado para la venta de mostrador (cliente
     * general), donde el producto se entrega en el momento del cobro.
     */
    public function marcarEntregadaMostrador(?int $usuarioId = null): self
    {
        $this->estado_envio = self::ENVIO_ENTREGADO;
        $this->entregado_at = now();
        $this->entregado_por = $usuarioId ?: $this->entregado_por;
        $this->save();

        $this->details()->update(['estado_despacho' => 1]);

        return $this;
    }

    /**
     * Determina si una venta puede/debe entregarse sola al crearse o al
     * aprobarse su pago: es venta de mostrador (sin cliente registrado, o
     * dado de alta al vuelo), no tiene fecha de entrega programada a
     * futuro, y su pago ya quedó confirmado.
     */
    public function esElegibleParaEntregaMostrador(): bool
    {
        if ($this->estatus !== self::ESTATUS_COMPLETADA) {
            return false;
        }

        if ($this->fecha_entrega && $this->fecha_entrega->toDateString() > now()->toDateString()) {
            return false;
        }

        return true;
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
