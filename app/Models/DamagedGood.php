<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedGood extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'source_type',
        'source_ref',
        'transacted_at',
        'note',
        'created_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(DamagedGoodItem::class, 'damaged_good_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
