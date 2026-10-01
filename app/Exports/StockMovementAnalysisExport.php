<?php

namespace App\Exports;

use App\Support\StockPeriodReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockMovementAnalysisExport implements WithMultipleSheets
{
    private ?array $preparedSheets = null;

    public function __construct(
        private array $filters,
        private string $requestedBy = '-'
    ) {}

    public function sheets(): array
    {
        if ($this->preparedSheets !== null) {
            return $this->preparedSheets;
        }

        $report = app(StockPeriodReport::class);
        $rows = $report->ordered($this->filters)->get();
        $trend = $report->dailyTrend($this->filters);

        return $this->preparedSheets = [
            new StockMovementSummarySheet($rows, $this->filters, $this->requestedBy),
            new StockMovementDetailSheet($rows),
            new StockMovementTrendSheet($trend),
        ];
    }
}
