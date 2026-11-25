<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'table_number', 'capacity', 'status', 'notes',
        'pos_x', 'pos_y', 'width', 'height', 'shape', 'section', 'rotation',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'pos_x'    => 'integer',
        'pos_y'    => 'integer',
        'width'    => 'integer',
        'height'   => 'integer',
        'rotation' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'table_number', 'table_number');
    }
}
