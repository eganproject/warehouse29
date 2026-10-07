<?php

namespace App\Exports;

use App\Models\PackerScanException;
use App\Models\PickingList;
use App\Support\PickingDemand;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PickingListExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /** @var array<string, array> [tanggal => sumber bundle per SKU] */
    private array $sourcesByDate = [];

    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = PickingList::query()
            ->with('item')
            ->orderBy('list_date', 'desc')
            ->orderBy('sku');
        $query->whereNotIn('sku', PackerScanException::query()->select('sku'));

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $date = $this->filters['date'] ?? null;
        if (empty($date)) {
            $date = now()->toDateString();
        }
        try {
            $target = Carbon::parse($date)->toDateString();
            $query->where('list_date', $target);
        } catch (\Throwable) {
            // ignore invalid date
        }

        $status = (string) ($this->filters['status'] ?? '');
        if ($status === 'ongoing') {
            $query->where('remaining_qty', '>', 0);
        } elseif ($status === 'done') {
            $query->where('remaining_qty', '<=', 0);
        }

        $rows = $query->get();
        foreach ($rows->map(fn ($row) => $row->list_date?->format('Y-m-d'))->filter()->unique() as $date) {
            $this->sourcesByDate[$date] = PickingDemand::bundleSources($date);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'SKU', 'Nama', 'Qty', 'Remaining', 'Termasuk dari Bundle'];
    }

    public function map($row): array
    {
        return [
            $row->list_date?->format('Y-m-d') ?? '-',
            $row->sku ?? '-',
            $row->item?->name ?? '-',
            (int) $row->qty,
            (int) $row->remaining_qty,
            PickingDemand::sourcesLabel($this->sourcesByDate[$row->list_date?->format('Y-m-d')][$row->sku] ?? []) ?? '',
        ];
    }
}
