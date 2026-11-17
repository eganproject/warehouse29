<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * API eksternal untuk membuat outbound (barang keluar) manual.
 *
 * Transaksi dibuat berstatus "pending" persis seperti input manual dari menu
 * Outbound Manual. Stok baru terpotong setelah transaksi disetujui (approve)
 * oleh admin gudang di WMS.
 */
class OutboundManualController extends Controller
{
    private const MAX_ITEMS = 200;

    public function store(Request $request): JsonResponse
    {
        if (! config('stock_api.outbound_manual_enabled')) {
            return $this->error('FORBIDDEN', 'API outbound manual sedang tidak aktif.', 403);
        }

        $validator = Validator::make($request->all(), [
            'ref_no' => ['required', 'string', 'max:100'],
            'transacted_at' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['array'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.stock_source' => ['nullable', 'in:regular,damaged'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ], [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'array' => ':attribute harus berupa array.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'in' => ':attribute harus salah satu dari: :values.',
            'min.numeric' => ':attribute minimal :min.',
            'max.numeric' => ':attribute maksimal :max.',
            'min.array' => ':attribute minimal berisi :min baris.',
            'max.array' => ':attribute maksimal berisi :max baris.',
            'max.string' => ':attribute maksimal :max karakter.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $validated = $validator->validated();
        $refNo = trim($validated['ref_no']);
        if ($refNo === '') {
            return $this->validationError(['ref_no' => ['ref_no wajib diisi.']]);
        }

        $transactedAt = CarbonImmutable::now('Asia/Jakarta');
        if (! empty($validated['transacted_at'])) {
            $transactedAt = $this->parseTimestamp($validated['transacted_at']);
            if (! $transactedAt) {
                return $this->validationError(['transacted_at' => ['Format transacted_at harus ISO-8601, mis. 2026-11-17T14:00:00+07:00.']]);
            }
            if ($transactedAt->greaterThan(CarbonImmutable::now('Asia/Jakarta')->addDay())) {
                return $this->validationError(['transacted_at' => ['transacted_at tidak boleh lebih dari 1 hari ke depan.']]);
            }
        }

        // Normalisasi baris: SKU dibandingkan tanpa beda huruf besar/kecil.
        $rows = collect($validated['items'])->map(fn (array $row) => [
            'sku' => trim($row['sku']),
            'qty' => (int) $row['qty'],
            'stock_source' => $row['stock_source'] ?? 'regular',
            'note' => isset($row['note']) && trim($row['note']) !== '' ? trim($row['note']) : null,
        ]);

        $errors = [];
        $seen = [];
        foreach ($rows as $i => $row) {
            $key = Str::upper($row['sku']).'|'.$row['stock_source'];
            if (isset($seen[$key])) {
                $errors["items.$i.sku"][] = "SKU {$row['sku']} dengan stock_source {$row['stock_source']} duplikat (sudah ada di items.{$seen[$key]}). Gabungkan qty-nya dalam satu baris.";
            }
            $seen[$key] ??= $i;
        }

        $items = Item::query()
            ->whereIn(DB::raw('UPPER(sku)'), $rows->pluck('sku')->map(fn ($s) => Str::upper($s))->unique()->values())
            ->get()
            ->keyBy(fn (Item $item) => Str::upper($item->sku));

        foreach ($rows as $i => $row) {
            $item = $items->get(Str::upper($row['sku']));
            if (! $item) {
                $errors["items.$i.sku"][] = "SKU {$row['sku']} tidak ditemukan.";
            } elseif (! $item->is_active) {
                $errors["items.$i.sku"][] = "SKU {$row['sku']} tidak aktif.";
            } elseif ($item->is_bundle && $row['stock_source'] === 'damaged') {
                $errors["items.$i.sku"][] = "SKU {$row['sku']} adalah bundle dan tidak bisa dikeluarkan dari stok rusak. Gunakan SKU komponennya.";
            }
        }

        if ($errors) {
            return $this->validationError($errors);
        }

        // Kunci per ref_no agar dua request bersamaan dengan ref_no sama tidak
        // menghasilkan dua transaksi.
        $lock = Cache::lock('api-outbound-manual:'.hash('sha256', Str::lower($refNo)), 10);
        if (! $lock->block(5)) {
            return $this->error('CONFLICT', 'Request lain dengan ref_no yang sama sedang diproses. Coba lagi sebentar.', 409);
        }

        try {
            $existing = OutboundTransaction::with('items.item')
                ->where('type', 'manual')
                ->where('ref_no', $refNo)
                ->orderBy('id')
                ->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DUPLICATE_REF_NO',
                        'message' => "Outbound manual dengan ref_no {$refNo} sudah ada ({$existing->code}).",
                    ],
                    'data' => $this->transform($existing),
                ], 409);
            }

            $tx = DB::transaction(function () use ($refNo, $validated, $transactedAt, $rows, $items) {
                $tx = OutboundTransaction::create([
                    'code' => 'OUT-MNL-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                    'type' => 'manual',
                    'ref_no' => $refNo,
                    'note' => $this->buildNote($validated['note'] ?? null),
                    'transacted_at' => $transactedAt->setTimezone(config('app.timezone')),
                    'created_by' => null,
                    'status' => 'pending',
                ]);

                foreach ($rows as $row) {
                    OutboundItem::create([
                        'outbound_transaction_id' => $tx->id,
                        'item_id' => $items->get(Str::upper($row['sku']))->id,
                        'stock_source' => $row['stock_source'],
                        'qty' => $row['qty'],
                        'note' => $row['note'],
                    ]);
                }

                return $tx;
            });
        } finally {
            $lock->release();
        }

        return response()->json([
            'success' => true,
            'message' => 'Outbound manual berhasil dibuat dan menunggu persetujuan admin gudang.',
            'data' => $this->transform($tx->fresh('items.item')),
        ], 201);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $tx = OutboundTransaction::with('items.item')
            ->where('type', 'manual')
            ->where(fn ($q) => $q->where('code', $code)->orWhere('ref_no', $code))
            ->orderBy('id')
            ->first();

        if (! $tx) {
            return $this->error('NOT_FOUND', "Outbound manual dengan kode atau ref_no {$code} tidak ditemukan.", 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transform($tx),
        ]);
    }

    private function transform(OutboundTransaction $tx): array
    {
        return [
            'code' => $tx->code,
            'ref_no' => $tx->ref_no,
            'type' => 'manual',
            'status' => $tx->status ?? 'pending',
            'transacted_at' => $this->iso($tx->transacted_at),
            'approved_at' => $this->iso($tx->approved_at),
            'note' => $tx->note,
            'total_qty' => (int) $tx->items->sum('qty'),
            'items' => $tx->items->map(fn (OutboundItem $row) => [
                'sku' => $row->item?->sku,
                'name' => $row->item?->name,
                'qty' => (int) $row->qty,
                'stock_source' => $row->stock_source ?? 'regular',
                'note' => $row->note,
            ])->values()->all(),
            'created_at' => $this->iso($tx->created_at),
        ];
    }

    private function buildNote(?string $note): string
    {
        $note = trim((string) $note);

        return $note === '' ? '[API]' : '[API] '.$note;
    }

    private function parseTimestamp(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone('Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }
    }

    private function iso($value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->setTimezone('Asia/Jakarta')->toIso8601String() : null;
    }

    private function validationError(array $fields): JsonResponse
    {
        $first = collect($fields)->flatten()->first() ?? 'Data tidak valid.';

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $first,
                'fields' => $fields,
            ],
        ], 422);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => compact('code', 'message')], $status);
    }
}
