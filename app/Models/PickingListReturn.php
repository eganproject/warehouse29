<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickingListReturn extends Model
{
    protected $fillable = [
        'list_date',
        'sku',
        'qty',
        'created_by',
    ];

    protected $casts = [
        'list_date' => 'date',
    ];
}
