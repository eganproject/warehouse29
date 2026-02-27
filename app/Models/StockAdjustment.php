<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'transacted_at',
        'note',
        'created_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
