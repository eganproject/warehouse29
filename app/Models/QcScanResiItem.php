<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QcScanResiItem extends Model
{
    protected $fillable = [
        'qc_scan_resi_id',
        'item_id',
        'sku',
        'required_qty',
        'scanned_qty',
    ];

    public function qcScanResi()
    {
        return $this->belongsTo(QcScanResi::class, 'qc_scan_resi_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /** Progres scan komponen (hanya terisi bila SKU baris ini adalah bundle). */
    public function bundleComponents()
    {
        return $this->hasMany(QcScanResiBundleComponent::class, 'qc_scan_resi_item_id');
    }
}
