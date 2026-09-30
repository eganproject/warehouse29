<?php

namespace Tests\Feature;

use App\Exports\InboundReturnsExport;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ReturnReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class InboundReturnsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_export_endpoint_downloads_an_xlsx_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.inbound.returns.index'))
            ->assertOk()
            ->assertSee('btn_export_flow', false)
            ->assertSee(route('admin.inbound.returns.export'), false);

        $response = $this->actingAs($user)->get(route('admin.inbound.returns.export', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'retur-inbound-20260901-sd-20260930.xlsx',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_workbook_contains_filtered_return_data_and_analytics(): void
    {
        $user = User::factory()->create(['name' => 'Supervisor Retur']);
        $item = Item::create(['sku' => 'SKU-RET-001', 'name' => 'Produk Retur']);
        $otherItem = Item::create(['sku' => 'SKU-OTHER', 'name' => 'Produk Lain']);
        $reason = ReturnReason::where('code', 'DAMAGED')->firstOrFail();

        $return = InboundTransaction::create([
            'code' => 'INB-RET-TEST-001',
            'type' => 'return',
            'ref_no' => 'REF-RET-001',
            'return_resi_no' => 'RESI-RET-001',
            'transacted_at' => '2026-09-20 10:00:00',
            'status' => 'finalized',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => '2026-09-20 11:00:00',
            'finalized_by' => $user->id,
            'finalized_at' => '2026-09-20 13:00:00',
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $return->id,
            'item_id' => $item->id,
            'qty' => 8,
            'qty_resi' => 10,
            'qty_received' => 8,
            'qty_difference' => 2,
            'qty_good' => 6,
            'qty_damaged' => 2,
            'return_reason_id' => $reason->id,
        ]);

        $outsidePeriod = InboundTransaction::create([
            'code' => 'INB-RET-OLD',
            'type' => 'return',
            'transacted_at' => '2026-09-01 10:00:00',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $outsidePeriod->id,
            'item_id' => $otherItem->id,
            'qty' => 99,
            'qty_resi' => 99,
            'qty_received' => 99,
            'qty_good' => 99,
        ]);

        $receipt = InboundTransaction::create([
            'code' => 'INB-RCV-TEST',
            'type' => 'receipt',
            'transacted_at' => '2026-09-20 12:00:00',
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $receipt->id,
            'item_id' => $item->id,
            'qty' => 50,
            'qty_received' => 50,
            'qty_good' => 50,
        ]);

        $binary = Excel::raw(new InboundReturnsExport([
            'q' => 'SKU-RET-001',
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-20',
        ], 'Supervisor Retur'), ExcelWriter::XLSX);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'return-export-');
        file_put_contents($temporaryFile, $binary);

        try {
            $spreadsheet = IOFactory::load($temporaryFile);

            $this->assertSame(
                ['Ringkasan', 'Detail Retur', 'Analisis SKU', 'Analisis Penyebab', 'Tren Harian', 'Analisis Kurir'],
                $spreadsheet->getSheetNames()
            );

            $summary = $spreadsheet->getSheetByName('Ringkasan');
            $this->assertSame('LAPORAN ANALITIK RETUR INBOUND', $summary->getCell('A1')->getValue());
            $this->assertSame(1, $summary->getCell('B11')->getValue());
            $this->assertSame(10, $summary->getCell('B14')->getValue());
            $this->assertSame(8, $summary->getCell('B15')->getValue());
            $this->assertSame(2, $summary->getCell('B16')->getValue());
            $this->assertSame(2, $summary->getCell('B19')->getValue());
            $this->assertSame(1, $summary->getCell('B22')->getValue());
            $this->assertSame(0, $summary->getCell('B26')->getValue());

            $detail = $spreadsheet->getSheetByName('Detail Retur');
            $this->assertSame('INB-RET-TEST-001', $detail->getCell('B2')->getValue());
            $this->assertSame('SKU-RET-001', $detail->getCell('I2')->getValue());
            $this->assertSame(10, $detail->getCell('K2')->getValue());
            $this->assertSame(8, $detail->getCell('L2')->getValue());
            $this->assertSame('Sesuai', $detail->getCell('AA2')->getValue());
            $this->assertSame(2, $detail->getHighestDataRow());
            $this->assertSame('A2', $detail->getFreezePane());

            $skuAnalysis = $spreadsheet->getSheetByName('Analisis SKU');
            $this->assertSame('SKU-RET-001', $skuAnalysis->getCell('B2')->getValue());
            $this->assertSame(8, $skuAnalysis->getCell('G2')->getValue());
            $this->assertSame(2, $skuAnalysis->getCell('K2')->getValue());

            $reasonAnalysis = $spreadsheet->getSheetByName('Analisis Penyebab');
            $this->assertSame('Barang rusak', $reasonAnalysis->getCell('B2')->getValue());
            $this->assertSame(8, $reasonAnalysis->getCell('G2')->getValue());

            $dailyTrend = $spreadsheet->getSheetByName('Tren Harian');
            $this->assertSame('2026-09-20', $dailyTrend->getCell('A2')->getValue());
            $this->assertSame(8, $dailyTrend->getCell('K2')->getValue());
            $this->assertSame(2, $dailyTrend->getCell('O2')->getValue());

            $courierAnalysis = $spreadsheet->getSheetByName('Analisis Kurir');
            $this->assertSame('Tidak diketahui / tanpa resi', $courierAnalysis->getCell('B2')->getValue());
            $this->assertSame(8, $courierAnalysis->getCell('H2')->getValue());
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }
            @unlink($temporaryFile);
        }
    }
}
