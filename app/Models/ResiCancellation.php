<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResiCancellation extends Model
{
    protected $fillable = [
        'resi_id',
        'qc_scan_resi_id',
        'packer_scan_out_id',
        'stage',
        'reason',
        'returned_stock_qty',
        'stock_returned_at',
        'canceled_by',
        'canceled_at',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'returned_stock_qty' => 'integer',
        'stock_returned_at' => 'datetime',
        'canceled_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function resi()
    {
        return $this->belongsTo(Resi::class);
    }

    public function qcScanResi()
    {
        return $this->belongsTo(QcScanResi::class);
    }

    public function packerScanOut()
    {
        return $this->belongsTo(PackerScanOut::class);
    }

    public function canceler()
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
