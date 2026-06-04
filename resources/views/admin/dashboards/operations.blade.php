@extends('layouts.admin')

@section('title', 'Operations Dashboard')
@section('page_title', 'Operations Dashboard')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <h3 class="fw-bolder mb-1">Monitoring Operasional Harian</h3>
                <div class="text-muted fs-7">Ringkasan progress QC, scan out, inbound, outbound, dan kesehatan stok.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <form method="get" class="d-flex align-items-center gap-2">
                <input type="text" name="date" id="filter_date" class="form-control form-control-solid w-150px" value="{{ $today }}" autocomplete="off">
                <button class="btn btn-primary" type="submit">Apply</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card bg-light border-0 h-100"><div class="card-body">
                    <div class="text-muted small">Total Resi Aktif</div>
                    <div class="fs-2 fw-bolder">{{ number_format($summary['total_resi']) }}</div>
                    <div class="text-muted small">Canceled: {{ number_format($summary['canceled_resi']) }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light border-0 h-100"><div class="card-body">
                    <div class="text-muted small">QC Scanned</div>
                    <div class="fs-2 fw-bolder text-primary">{{ number_format($summary['qc_scanned']) }}</div>
                    <div class="text-muted small">{{ $summary['qc_rate'] }}% dari resi aktif</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light border-0 h-100"><div class="card-body">
                    <div class="text-muted small">Scan Out</div>
                    <div class="fs-2 fw-bolder text-success">{{ number_format($summary['scan_out']) }}</div>
                    <div class="text-muted small">{{ $summary['scan_out_rate'] }}% selesai</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light border-0 h-100"><div class="card-body">
                    <div class="text-muted small">Remaining Scan Out</div>
                    <div class="fs-2 fw-bolder text-danger">{{ number_format($summary['remaining_scan_out']) }}</div>
                    <div class="text-muted small">Belum scan out</div>
                </div></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-6 mb-6">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-6"><h3 class="card-title fw-bolder">Transaksi Hari Ini</h3></div>
            <div class="card-body pt-2">
                <div class="row g-4">
                    <div class="col-6"><div class="p-4 bg-light rounded"><div class="text-muted small">Inbound Receipt</div><div class="fs-3 fw-bolder">{{ number_format($summary['inbound_receipt']) }}</div></div></div>
                    <div class="col-6"><div class="p-4 bg-light rounded"><div class="text-muted small">Inbound Return</div><div class="fs-3 fw-bolder">{{ number_format($summary['inbound_return']) }}</div></div></div>
                    <div class="col-6"><div class="p-4 bg-light rounded"><div class="text-muted small">Outbound Manual</div><div class="fs-3 fw-bolder">{{ number_format($summary['outbound_manual']) }}</div></div></div>
                    <div class="col-6"><div class="p-4 bg-light rounded"><div class="text-muted small">Outbound Return</div><div class="fs-3 fw-bolder">{{ number_format($summary['outbound_return']) }}</div></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-6"><h3 class="card-title fw-bolder">Risiko Stok</h3></div>
            <div class="card-body pt-2">
                <div class="row g-4">
                    <div class="col-6"><div class="p-4 bg-light-danger rounded"><div class="text-muted small">Out of Stock</div><div class="fs-3 fw-bolder text-danger">{{ number_format($summary['out_of_stock']) }}</div></div></div>
                    <div class="col-6"><div class="p-4 bg-light-warning rounded"><div class="text-muted small">Low Stock</div><div class="fs-3 fw-bolder text-warning">{{ number_format($summary['low_stock']) }}</div></div></div>
                    <div class="col-12"><div class="text-muted fs-7">Gunakan Stock Health Report untuk melihat SKU detail, gap terhadap safety stock, dan mutasi terakhir.</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title fw-bolder">Bottleneck Per Kurir</h3>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase">
                        <th>Kurir</th>
                        <th class="text-end">Resi</th>
                        <th class="text-end">Scan Out</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Completion</th>
                        <th>Last Scan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($couriers as $row)
                        <tr>
                            <td class="fw-bold">{{ $row['name'] }}</td>
                            <td class="text-end">{{ number_format($row['total_resi']) }}</td>
                            <td class="text-end">{{ number_format($row['scan_total']) }}</td>
                            <td class="text-end fw-bolder {{ $row['remaining'] > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($row['remaining']) }}</td>
                            <td class="text-end">{{ $row['completion_rate'] }}%</td>
                            <td>{{ $row['last_scan_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Tidak ada data operasional pada tanggal ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dateInput = document.getElementById('filter_date');
        if (typeof flatpickr !== 'undefined' && dateInput) {
            flatpickr(dateInput, { dateFormat: 'Y-m-d', allowInput: true });
        }
    });
</script>
@endpush
