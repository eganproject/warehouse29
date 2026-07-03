<?php

namespace App\Exports;

use App\Models\InboundItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InboundReturnsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = InboundItem::query()
            ->with([
                'transaction.resi.kurir',
                'transaction.creator',
                'item',
                'returnReason',
            ])
            ->whereHas('transaction', function ($transactionQ) {
                $transactionQ->where('type', 'return');
                $this->applyTransactionFilters($transactionQ);
            })
            ->orderBy(
                \App\Models\InboundTransaction::select('transacted_at')
                    ->whereColumn('inbound_transactions.id', 'inbound_items.inbound_transaction_id'),
                'desc'
            )
            ->orderBy('inbound_items.id');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Kode Retur',
            'Tanggal',
            'Status',
            'No Resi',
            'ID Pesanan',
            'Kurir',
            'SKU',
            'Nama Item',
            'Qty Resi',
            'Qty Diterima',
            'Selisih',
            'Qty Bagus',
            'Qty Rusak',
            'Penyebab',
            'Catatan Penyebab',
            'Catatan Item',
            'Catatan Retur',
            'Submit By',
        ];
    }

    public function map($row): array
    {
        $tx = $row->transaction;
        $resi = $tx?->resi;

        return [
            $tx?->code ?? '-',
            $tx?->transacted_at ? Carbon::parse($tx->transacted_at)->format('Y-m-d H:i') : '-',
            $this->statusLabel($tx?->status ?? 'pending'),
            $tx?->return_resi_no ?: ($resi?->no_resi ?: '-'),
            $resi?->id_pesanan ?? '-',
            $resi?->kurir?->name ?? '-',
            $row->item?->sku ?? '-',
            $row->item?->name ?? '-',
            (int) ($row->qty_resi ?? $row->qty_received ?? $row->qty ?? 0),
            (int) ($row->qty_received ?? $row->qty ?? 0),
            (int) ($row->qty_difference ?? 0),
            (int) ($row->qty_good ?? 0),
            (int) ($row->qty_damaged ?? 0),
            $row->returnReason?->name ?? '-',
            $row->return_reason_note ?? '',
            $row->note ?? '',
            $tx?->note ?? '',
            $tx?->creator?->name ?? '-',
        ];
    }

    private function applyTransactionFilters($query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%")
                    ->orWhere('return_resi_no', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('no_resi', 'like', "%{$search}%")
                            ->orWhere('id_pesanan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
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
            // ignore invalid date filters
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'finalized' => 'Finalisasi',
            'approved' => 'Gudang Retur',
            default => 'Menunggu',
        };
    }
}
