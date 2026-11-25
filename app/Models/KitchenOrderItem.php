<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenOrderItem extends Model
{
    protected $fillable = [
        'kitchen_order_id', 'sale_item_id', 'product_id', 'quantity',
        'modifier_summary', 'special_instructions',
        'void_reason', 'voided_by', 'voided_at',
    ];

    protected $casts = [
        'quantity'  => 'float',
        'voided_at' => 'datetime',
    ];

    public function kitchenOrder()
    {
        return $this->belongsTo(KitchenOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
