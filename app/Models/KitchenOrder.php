<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenOrder extends Model
{
    protected $fillable = [
        'branch_id', 'sale_id', 'kot_number', 'station',
        'status', 'priority', 'notes', 'printed_at',
    ];

    protected $casts = [
        'priority'   => 'boolean',
        'printed_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(KitchenOrderItem::class);
    }
}
