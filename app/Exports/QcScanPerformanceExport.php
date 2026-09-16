<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class QcScanPerformanceExport implements WithMultipleSheets
{
    public function __construct(
        private array $filters = [],
        private ?int $allowedDivisiId = null,
        private string $generatedBy = '-'
    ) {}

    public function sheets(): array
    {
        return [
            new QcScanPerformanceInfoSheet($this->filters, $this->generatedBy),
            new QcScanPerformanceDataSheet('daily', $this->filters, $this->allowedDivisiId),
            new QcScanPerformanceDataSheet('hourly', $this->filters, $this->allowedDivisiId),
            new QcScanPerformanceDataSheet('detail', $this->filters, $this->allowedDivisiId),
        ];
    }
}
