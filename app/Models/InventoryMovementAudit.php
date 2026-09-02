<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovementAudit extends Model
{
    protected $table = 'inventory_movement_audits';

    protected $fillable = [
        'inventory_movement_id',
        'reception_id',
        'action',
        'old_quantity',
        'new_quantity',
        'old_unit_cost',
        'new_unit_cost',
        'old_total_cost',
        'new_total_cost',
        'old_notes',
        'new_notes',
        'user_id',
    ];

    protected $casts = [
        'old_quantity' => 'decimal:3',
        'new_quantity' => 'decimal:3',
        'old_unit_cost' => 'decimal:2',
        'new_unit_cost' => 'decimal:2',
        'old_total_cost' => 'decimal:2',
        'new_total_cost' => 'decimal:2',
    ];

    public function movement()
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
