<?php

namespace App\Exports;

/**
 * Analisis retur per toko asal resi (struktur kolom sama dengan Analisis Kurir).
 */
class InboundReturnsTokoAnalysisSheet extends InboundReturnsCourierAnalysisSheet
{
    public function title(): string
    {
        return 'Analisis Toko';
    }

    protected function groupHeading(): string
    {
        return 'Toko';
    }

    protected function groupKey($transaction): int|string
    {
        return $transaction->resi?->toko_id ?: 'unknown';
    }

    protected function groupLabel($transaction): string
    {
        return $this->safeText($transaction->resi?->toko?->name, 'Tanpa toko / tanpa resi');
    }
}
