<?php

namespace App\Exports;

use App\Support\TokoReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TokoReportExport implements WithMultipleSheets
{
    public function __construct(
        private array $filters,
        private string $generatedBy = '-'
    ) {}

    public function sheets(): array
    {
        $perToko = TokoReport::perToko($this->filters);

        return [
            new TokoReportInfoSheet($this->filters, TokoReport::summary($perToko), $this->generatedBy),
            new TokoReportSummarySheet($perToko),
            new TokoReportDailySheet(TokoReport::daily($this->filters)),
            new TokoReportDetailSheet($this->filters),
        ];
    }
}
