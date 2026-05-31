<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'direction',
        'qty',
        'stock_before',
        'stock_after',
        'source_type',
        'source_subtype',
        'source_id',
        'source_code',
        'note',
        'occurred_at',
        'created_by',
        'idempotency_key',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockBeforeValue(): ?int
    {
        if ($this->stock_before !== null) {
            return (int) $this->stock_before;
        }

        if (! $this->item_id || ! $this->id) {
            return null;
        }

        $occurredAt = $this->occurred_at;

        return (int) static::query()
            ->where('item_id', $this->item_id)
            ->where(function ($query) use ($occurredAt) {
                if ($occurredAt) {
                    $query->where('occurred_at', '<', $occurredAt)
                        ->orWhere(function ($sameTime) use ($occurredAt) {
                            $sameTime->where('occurred_at', $occurredAt)
                                ->where('id', '<', $this->id);
                        });
                } else {
                    $query->where('id', '<', $this->id);
                }
            })
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END), 0) as stock")
            ->value('stock');
    }

    public function stockAfterValue(): ?int
    {
        if ($this->stock_after !== null) {
            return (int) $this->stock_after;
        }

        $before = $this->stockBeforeValue();
        if ($before === null) {
            return null;
        }

        return $this->direction === 'out'
            ? $before - (int) $this->qty
            : $before + (int) $this->qty;
    }
}
