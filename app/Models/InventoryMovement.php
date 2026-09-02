<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_ENTRY = 'entrada';
    public const TYPE_EXIT = 'salida';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'inventory_movements';

    protected $fillable = [
        'product_id',
        'sale_id',
        'sale_detail_id',
        'user_id',
        'reception_id',
        'type',
        'origin',
        'quantity',
        'balance_after',
        'unit',
        'unit_cost',
        'total_cost',
        'notes',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'moved_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'moved_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function audits()
    {
        return $this->hasMany(InventoryMovementAudit::class, 'inventory_movement_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeEntries($query)
    {
        return $query->where('type', self::TYPE_ENTRY);
    }

    public function scopeExits($query)
    {
        return $query->where('type', self::TYPE_EXIT);
    }
}
