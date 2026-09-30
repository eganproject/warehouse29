<?php

namespace App\Exports;

use App\Models\InboundTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InboundReturnsExport implements WithMultipleSheets
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
            new InboundReturnsSummarySheet($transactions, $this->filters, $this->requestedBy),
            new InboundReturnsDetailSheet($transactions),
            new InboundReturnsItemAnalysisSheet($transactions),
            new InboundReturnsReasonAnalysisSheet($transactions),
            new InboundReturnsDailyTrendSheet($transactions),
            new InboundReturnsCourierAnalysisSheet($transactions),
        ];
    }

    private function transactions(): Collection
    {
        $query = InboundTransaction::query()
            ->with([
                'items.item',
                'items.returnReason',
                'resi.kurir',
                'creator',
                'approver',
                'finalizer',
            ])
            ->where('type', 'return')
            ->orderBy('transacted_at')
            ->orderBy('id');

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('code', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%")
                    ->orWhere('return_resi_no', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQuery) use ($search) {
                        $resiQuery->where('no_resi', 'like', "%{$search}%")
                            ->orWhere('id_pesanan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('creator', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items', function ($itemLineQuery) use ($search) {
                        $itemLineQuery->where('note', 'like', "%{$search}%")
                            ->orWhere('return_reason_note', 'like', "%{$search}%")
                            ->orWhereHas('item', function ($itemQuery) use ($search) {
                                $itemQuery->where('sku', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('returnReason', fn ($reasonQuery) => $reasonQuery->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        $status = trim((string) ($this->filters['status'] ?? ''));
        if (in_array($status, ['pending', 'approved', 'finalized'], true)) {
            $query->where('status', $status);
        }

        if (!empty($this->filters['date_from'])) {
            $query->where('transacted_at', '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
        }
        if (!empty($this->filters['date_to'])) {
            $query->where('transacted_at', '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
        }

        return $query->get();
    }
}
