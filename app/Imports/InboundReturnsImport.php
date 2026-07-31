<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Resi;
use App\Models\ReturnReason;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InboundReturnsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<string,array{ref_no:?string,resi_id:?int,return_resi_no:?string,note:?string,transacted_at:?string,items:array<int,array{item_id:int,qty:int,qty_resi:int,qty_received:int,qty_difference:int,qty_good:int,qty_damaged:int,return_reason_id:?int,return_reason_note:?string,note:?string}>}> */
    public array $groups = [];

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headers = array_keys($first?->toArray() ?? []);
        if (!in_array('sku', $headers, true)) {
            throw ValidationException::withMessages([
                'file' => 'Header wajib: sku, qty_diterima, qty_bagus, qty_rusak (opsional: no_resi, id_pesanan, qty_resi, return_reason, ref_no, note, item_note, transacted_at)',
            ]);
        }
        $qtyResiKey = $this->detectFirstKey($headers, ['qty_resi', 'qty_order', 'qty_pesanan']);
        $receivedKey = $this->detectFirstKey($headers, ['qty_diterima', 'qty_received', 'diterima', 'received', 'qty']);
        if ($receivedKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header qty_diterima wajib (bisa gunakan: qty_diterima/qty_received/diterima/received/qty)',
            ]);
        }
        $goodKey = $this->detectFirstKey($headers, ['qty_bagus', 'qty_good', 'bagus', 'good']);
        $damagedKey = $this->detectFirstKey($headers, ['qty_rusak', 'qty_damaged', 'rusak', 'damaged']);
        if ($goodKey === null || $damagedKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header qty_bagus dan qty_rusak wajib agar qty diterima bisa divalidasi balance.',
            ]);
        }

        $skus = $rows->map(fn ($row) => trim((string) ($row['sku'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $items = Item::active()->whereIn('sku', $skus)->get(['id', 'sku']);
        $skuMap = $items->pluck('id', 'sku')->all();
        $reasonMap = ReturnReason::active()
            ->get(['id', 'code', 'name'])
            ->flatMap(function ($reason) {
                return [
                    strtolower($reason->code) => $reason->id,
                    strtolower($reason->name) => $reason->id,
                ];
            })->all();

        $missing = [];
        $errors = [];
        $rowIndex = 1;
        foreach ($rows as $row) {
            $rowIndex++;
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            if (!isset($skuMap[$sku])) {
                $missing[$sku] = true;
                continue;
            }
            $qtyResi = $qtyResiKey ? $this->parseQty($row, $qtyResiKey) : 0;
            $receivedQty = $this->parseQty($row, $receivedKey);
            $goodQty = $this->parseQty($row, $goodKey);
            $damagedQty = $this->parseQty($row, $damagedKey);
            $qtyResi = $qtyResi > 0 ? $qtyResi : $receivedQty;

            if ($qtyResi <= 0 || $receivedQty > $qtyResi) {
                $errors[] = "Baris {$rowIndex}: qty resi wajib lebih dari 0 dan qty diterima tidak boleh lebih besar dari qty resi untuk SKU {$sku}";
                continue;
            }
            if ($goodQty + $damagedQty !== $receivedQty) {
                $errors[] = "Baris {$rowIndex}: qty bagus + qty rusak harus sama dengan qty diterima untuk SKU {$sku}";
                continue;
            }

            $ref = trim((string) ($row['ref_no'] ?? ''));
            $noResi = trim((string) ($row['no_resi'] ?? ''));
            $idPesanan = trim((string) ($row['id_pesanan'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));
            $itemNote = trim((string) ($row['item_note'] ?? $row['note_item'] ?? ''));
            $reasonRaw = trim((string) ($row['return_reason'] ?? $row['penyebab'] ?? ''));
            $reasonNote = trim((string) ($row['return_reason_note'] ?? $row['catatan_penyebab'] ?? ''));
            $transactedAt = trim((string) ($row['transacted_at'] ?? $row['tanggal'] ?? ''));
            $resi = null;
            if ($noResi !== '' || $idPesanan !== '') {
                $resi = Resi::query()
                    ->when($noResi !== '', fn ($query) => $query->where('no_resi', $noResi))
                    ->when($noResi === '' && $idPesanan !== '', fn ($query) => $query->where('id_pesanan', $idPesanan))
                    ->first(['id', 'no_resi', 'id_pesanan']);
            }
            $returnResiNo = $noResi !== '' ? $noResi : ($idPesanan !== '' ? $idPesanan : null);
            $reasonId = $reasonRaw !== '' ? ($reasonMap[strtolower($reasonRaw)] ?? null) : null;

            $groupKey = $returnResiNo ?: ($ref !== '' ? $ref : '__default__');
            if (!isset($this->groups[$groupKey])) {
                $this->groups[$groupKey] = [
                    'ref_no' => $ref !== '' ? $ref : null,
                    'resi_id' => $resi?->id,
                    'return_resi_no' => $returnResiNo,
                    'note' => $note !== '' ? $note : null,
                    'transacted_at' => $transactedAt !== '' ? $transactedAt : null,
                    'items' => [],
                ];
            } else {
                if ($this->groups[$groupKey]['note'] === null && $note !== '') {
                    $this->groups[$groupKey]['note'] = $note;
                }
                if ($this->groups[$groupKey]['transacted_at'] === null && $transactedAt !== '') {
                    $this->groups[$groupKey]['transacted_at'] = $transactedAt;
                }
                if ($this->groups[$groupKey]['resi_id'] === null && $resi) {
                    $this->groups[$groupKey]['resi_id'] = $resi->id;
                }
            }

            $itemId = (int) $skuMap[$sku];
            if (!isset($this->groups[$groupKey]['items'][$itemId])) {
                $this->groups[$groupKey]['items'][$itemId] = [
                    'item_id' => $itemId,
                    'qty' => $receivedQty,
                    'qty_resi' => $qtyResi,
                    'qty_received' => $receivedQty,
                    'qty_difference' => max($qtyResi - $receivedQty, 0),
                    'qty_good' => $goodQty,
                    'qty_damaged' => $damagedQty,
                    'return_reason_id' => $reasonId,
                    'return_reason_note' => $reasonNote !== '' ? $reasonNote : null,
                    'note' => $itemNote !== '' ? $itemNote : null,
                ];
            } else {
                $this->groups[$groupKey]['items'][$itemId]['qty'] += $receivedQty;
                $this->groups[$groupKey]['items'][$itemId]['qty_resi'] += $qtyResi;
                $this->groups[$groupKey]['items'][$itemId]['qty_received'] += $receivedQty;
                $this->groups[$groupKey]['items'][$itemId]['qty_difference'] = max(
                    $this->groups[$groupKey]['items'][$itemId]['qty_resi'] - $this->groups[$groupKey]['items'][$itemId]['qty_received'],
                    0
                );
                $this->groups[$groupKey]['items'][$itemId]['qty_good'] += $goodQty;
                $this->groups[$groupKey]['items'][$itemId]['qty_damaged'] += $damagedQty;
                if ($reasonId && empty($this->groups[$groupKey]['items'][$itemId]['return_reason_id'])) {
                    $this->groups[$groupKey]['items'][$itemId]['return_reason_id'] = $reasonId;
                }
                if ($reasonNote !== '' && empty($this->groups[$groupKey]['items'][$itemId]['return_reason_note'])) {
                    $this->groups[$groupKey]['items'][$itemId]['return_reason_note'] = $reasonNote;
                }
                if ($itemNote !== '' && empty($this->groups[$groupKey]['items'][$itemId]['note'])) {
                    $this->groups[$groupKey]['items'][$itemId]['note'] = $itemNote;
                }
            }
        }

        if (!empty($missing)) {
            $list = implode(', ', array_keys($missing));
            throw ValidationException::withMessages([
                'file' => 'SKU tidak ditemukan: '.$list,
            ]);
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 5)),
            ]);
        }

        foreach ($this->groups as $key => $group) {
            $items = array_values($group['items'] ?? []);
            if (empty($items)) {
                unset($this->groups[$key]);
                continue;
            }
            $this->groups[$key]['items'] = $items;
        }

        if (empty($this->groups)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }
    }

    private function detectFirstKey(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            if (in_array($key, $headers, true)) {
                return $key;
            }
        }
        return null;
    }

    private function parseQty($row, string $key): int
    {
        $raw = null;
        if (is_array($row) && array_key_exists($key, $row)) {
            $raw = $row[$key];
        } elseif ($row instanceof Collection && $row->has($key)) {
            $raw = $row->get($key);
        } elseif (isset($row[$key])) {
            $raw = $row[$key];
        }
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
    }
}
