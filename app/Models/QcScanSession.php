<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QcScanSession extends Model
{
    protected $fillable = [
        'code',
        'user_id',
        'status',
        'started_at',
        'last_scan_at',
        'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_scan_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resis()
    {
        return $this->hasMany(QcScanResi::class, 'qc_scan_session_id');
    }
}
