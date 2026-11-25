<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'invoice_number', 'customer_id', 'user_id', 'subtotal',
        'discount', 'tax', 'service_charge', 'total', 'payment_method', 'card_reference',
        'payment_status', 'amount_paid', 'notes', 'sold_at', 'status', 'table_number',
        'order_type', 'waiter_id', 'guest_count', 'sequence_number',
        'pickup_time', 'delivery_phone', 'delivery_address', 'delivery_fee', 'delivery_status',
        'parent_sale_id',
    ];

    protected $casts = [
        'sold_at'        => 'datetime',
        'pickup_time'    => 'datetime',
        'subtotal'       => 'float',
        'discount'       => 'float',
        'tax'            => 'float',
        'service_charge' => 'float',
        'delivery_fee'   => 'float',
        'total'          => 'float',
        'amount_paid'    => 'float',
        'guest_count'    => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function parentSale()
    {
        return $this->belongsTo(Sale::class, 'parent_sale_id');
    }

    public function mergedSales()
    {
        return $this->hasMany(Sale::class, 'parent_sale_id');
    }
}
