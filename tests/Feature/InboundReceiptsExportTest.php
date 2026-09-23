<?php

namespace Tests\Feature;

use App\Exports\InboundReceiptsExport;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class InboundReceiptsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_export_endpoint_downloads_an_xlsx_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.inbound.receipts.index'))
            ->assertOk()
            ->assertSee('btn_export_flow', false)
            ->assertSee(route('admin.inbound.receipts.export'), false);

        $response = $this->actingAs($user)->get(route('admin.inbound.receipts.export', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'laporan-penerimaan-barang-20260901-sd-20260930.xlsx',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_workbook_contains_filtered_receipt_data_and_analysis_sheets(): void
    {
        $user = User::factory()->create(['name' => 'Petugas Inbound']);
        $item = Item::create(['sku' => 'SKU-RCV-001', 'name' => 'Produk Penerimaan']);
        $otherItem = Item::create(['sku' => 'SKU-OTHER', 'name' => 'Produk Lain']);

        $receipt = InboundTransaction::create([
            'code' => 'INB-RCV-TEST-001',
            'type' => 'receipt',
            'ref_no' => 'PO-001',
            'transacted_at' => '2026-09-20 10:00:00',
            'status' => 'approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => '2026-09-20 11:00:00',
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $receipt->id,
            'item_id' => $item->id,
            'qty' => 12,
            'qty_received' => 12,
        ]);

        $outsidePeriod = InboundTransaction::create([
            'code' => 'INB-RCV-OLD',
            'type' => 'receipt',
            'transacted_at' => '2026-09-01 10:00:00',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $outsidePeriod->id,
            'item_id' => $otherItem->id,
            'qty' => 99,
            'qty_received' => 99,
        ]);

        $return = InboundTransaction::create([
            'code' => 'INB-RET-TEST',
            'type' => 'return',
            'transacted_at' => '2026-09-20 12:00:00',
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $return->id,
            'item_id' => $item->id,
            'qty' => 50,
            'qty_received' => 50,
        ]);

        $binary = Excel::raw(new InboundReceiptsExport([
            'q' => 'SKU-RCV',
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-20',
        ], 'Supervisor Gudang'), ExcelWriter::XLSX);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'receipt-export-');
        file_put_contents($temporaryFile, $binary);

        try {
            $spreadsheet = IOFactory::load($temporaryFile);

            $this->assertSame(
                ['Ringkasan', 'Detail Penerimaan', 'Analisis SKU', 'Tren Harian'],
                $spreadsheet->getSheetNames()
            );

            $summary = $spreadsheet->getSheetByName('Ringkasan');
            $this->assertSame('LAPORAN PENERIMAAN BARANG', $summary->getCell('A1')->getValue());
            $this->assertSame(1, $summary->getCell('B10')->getValue());
            $this->assertSame(12, $summary->getCell('B11')->getValue());

            $detail = $spreadsheet->getSheetByName('Detail Penerimaan');
            $this->assertSame('INB-RCV-TEST-001', $detail->getCell('B2')->getValue());
            $this->assertSame('SKU-RCV-001', $detail->getCell('F2')->getValue());
            $this->assertSame(12, $detail->getCell('H2')->getValue());
            $this->assertSame(2, $detail->getHighestDataRow());
            $this->assertSame('A2', $detail->getFreezePane());

            $skuAnalysis = $spreadsheet->getSheetByName('Analisis SKU');
            $this->assertSame('SKU-RCV-001', $skuAnalysis->getCell('B2')->getValue());
            $this->assertSame(12, $skuAnalysis->getCell('F2')->getValue());

            $dailyTrend = $spreadsheet->getSheetByName('Tren Harian');
            $this->assertSame('2026-09-20', $dailyTrend->getCell('A2')->getValue());
            $this->assertSame(12, $dailyTrend->getCell('H2')->getValue());
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }
            @unlink($temporaryFile);
        }
    }
}
