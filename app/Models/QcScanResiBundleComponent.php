<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QcScanResiBundleComponent extends Model
{
    protected $fillable = [
        'qc_scan_resi_item_id',
        'component_item_id',
        'component_sku',
        'qty_per_bundle',
        'scanned_qty',
    ];

    protected $casts = [
        'qty_per_bundle' => 'integer',
        'scanned_qty' => 'integer',
    ];

    public function qcScanResiItem()
    {
        return $this->belongsTo(QcScanResiItem::class, 'qc_scan_resi_item_id');
    }

    public function componentItem()
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }
}
