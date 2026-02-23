<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'ref_no',
        'transacted_at',
        'note',
        'created_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OutboundItem::class, 'outbound_transaction_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
