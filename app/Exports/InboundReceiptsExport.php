<?php

namespace App\Exports;

use App\Models\InboundTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InboundReceiptsExport implements WithMultipleSheets
{
    public function __construct(
        private array $filters = [],
        private string $requestedBy = '-'
    ) {
    }

    public function sheets(): array
    {
        $transactions = $this->transactions();

        return [
            new InboundReceiptsSummarySheet($transactions, $this->filters, $this->requestedBy),
            new InboundReceiptsDetailSheet($transactions),
            new InboundReceiptsItemSummarySheet($transactions),
            new InboundReceiptsDailyTrendSheet($transactions),
        ];
    }

    private function transactions(): Collection
    {
        $query = InboundTransaction::query()
            ->with(['items.item', 'creator', 'approver'])
            ->where('type', 'receipt')
            ->orderBy('transacted_at')
            ->orderBy('id');

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('code', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQuery) use ($search) {
                        $itemQuery->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $status = trim((string) ($this->filters['status'] ?? ''));
        if (in_array($status, ['pending', 'approved', 'finalized'], true)) {
            $query->where('status', $status);
        }

        try {
            if (!empty($this->filters['date_from'])) {
                $query->where('transacted_at', '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
            }
            if (!empty($this->filters['date_to'])) {
                $query->where('transacted_at', '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
            }
        } catch (\Throwable) {
            // Invalid date values are ignored, consistently with the list page.
        }

        return $query->get();
    }
}
