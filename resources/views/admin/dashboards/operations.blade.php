@extends('layouts.admin')

@section('title', 'Operations Dashboard')
@section('page_title', 'Operations Dashboard')

@section('content')
@php
    $scanRate = min(100, max(0, (float) ($summary['scan_out_rate'] ?? 0)));
    $qcRate = min(100, max(0, (float) ($summary['qc_rate'] ?? 0)));
    $pendingRate = $summary['total_resi'] > 0
        ? min(100, max(0, round(($summary['remaining_scan_out'] / $summary['total_resi']) * 100, 1)))
        : 0;
    $riskTotal = (int) ($summary['out_of_stock'] ?? 0) + (int) ($summary['low_stock'] ?? 0);
@endphp

<style>
    .ops-page {
        --ops-ink: #172033;
        --ops-muted: #64748b;
        --ops-line: #e5e9f2;
        --ops-blue: #2563eb;
        --ops-teal: #0f766e;
        --ops-amber: #d97706;
        --ops-red: #dc2626;
        --ops-violet: #7c3aed;
    }

    .ops-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid #dbe4f0;
        border-radius: 8px;
        background:
            linear-gradient(135deg, rgba(15, 118, 110, 0.96), rgba(37, 99, 235, 0.94)),
            url("{{ asset('metronic/media/stock/2000x800/1.jpg') }}");
        background-size: cover;
        background-position: center;
        color: #fff;
        padding: clamp(22px, 3vw, 34px);
        margin-bottom: 24px;
    }

    .ops-hero::after {
        content: "";
        position: absolute;
        inset: auto 0 0 0;
        height: 1px;
        background: rgba(255,255,255,0.35);
    }

    .ops-hero-inner {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 24px;
        align-items: end;
    }

    .ops-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 5px 10px;
        border: 1px solid rgba(255,255,255,0.35);
        border-radius: 999px;
        background: rgba(255,255,255,0.12);
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 12px;
    }

    .ops-title {
        font-size: clamp(26px, 3vw, 38px);
        line-height: 1.08;
        font-weight: 800;
        letter-spacing: 0;
        margin: 0;
    }

    .ops-subtitle {
        max-width: 720px;
        color: rgba(255,255,255,0.82);
        font-size: 13px;
        margin-top: 10px;
    }

    .ops-date-form {
        display: flex;
        gap: 8px;
        align-items: center;
        padding: 8px;
        border-radius: 8px;
        background: rgba(255,255,255,0.16);
        border: 1px solid rgba(255,255,255,0.25);
        backdrop-filter: blur(6px);
    }

    .ops-date-form .form-control {
        width: 150px;
        border-color: rgba(255,255,255,0.35);
        background: rgba(255,255,255,0.96);
    }

    .ops-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .ops-metric {
        border: 1px solid var(--ops-line);
        border-radius: 8px;
        background: #fff;
        padding: 18px;
        min-height: 158px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .ops-metric-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }

    .ops-icon {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        color: var(--accent);
        background: rgba(37, 99, 235, 0.1);
        background: color-mix(in srgb, var(--accent) 13%, white);
        font-size: 18px;
    }

    .ops-label {
        color: var(--ops-muted);
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .ops-value {
        color: var(--ops-ink);
        font-size: 32px;
        font-weight: 800;
        line-height: 1;
    }

    .ops-meta {
        color: var(--ops-muted);
        font-size: 12px;
        margin-top: 7px;
    }

    .ops-progress {
        height: 7px;
        background: #eef2f7;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 14px;
    }

    .ops-progress > span {
        display: block;
        height: 100%;
        width: var(--value);
        background: var(--accent);
        border-radius: inherit;
    }

    .ops-panel {
        border: 1px solid var(--ops-line);
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        height: 100%;
    }

    .ops-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 20px 22px 8px;
    }

    .ops-panel-title {
        color: var(--ops-ink);
        font-size: 17px;
        font-weight: 800;
        margin: 0;
    }

    .ops-panel-subtitle {
        color: var(--ops-muted);
        font-size: 12px;
        margin-top: 3px;
    }

    .ops-tile-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        padding: 14px 22px 22px;
    }

    .ops-tile {
        border: 1px solid #edf1f6;
        border-radius: 8px;
        padding: 14px;
        background: #fbfcfe;
    }

    .ops-tile-value {
        color: var(--ops-ink);
        font-size: 24px;
        font-weight: 800;
        line-height: 1.05;
        margin-top: 5px;
    }

    .ops-risk-strip {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding: 14px 22px 0;
    }

    .ops-risk {
        border-radius: 8px;
        padding: 16px;
        color: #fff;
        background: var(--accent);
    }

    .ops-risk .ops-label,
    .ops-risk .ops-meta {
        color: rgba(255,255,255,0.78);
    }

    .ops-risk .ops-value {
        color: #fff;
    }

    .ops-table-card {
        border: 1px solid var(--ops-line);
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .ops-table-card .table > :not(caption) > * > * {
        padding: 14px 18px;
    }

    .ops-courier {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 180px;
    }

    .ops-avatar {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eef6ff;
        color: var(--ops-blue);
        font-weight: 800;
        flex: 0 0 auto;
    }

    .ops-mini-progress {
        min-width: 150px;
    }

    .ops-mini-progress .ops-progress {
        margin-top: 0;
        height: 6px;
    }

    @media (max-width: 1199.98px) {
        .ops-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 767.98px) {
        .ops-hero-inner { grid-template-columns: 1fr; }
        .ops-date-form { align-items: stretch; flex-wrap: wrap; }
        .ops-date-form .form-control { width: 100%; }
        .ops-grid,
        .ops-tile-grid,
        .ops-risk-strip { grid-template-columns: 1fr; }
    }
</style>

<div class="ops-page">
    <section class="ops-hero">
        <div class="ops-hero-inner">
            <div>
                <div class="ops-eyebrow">
                    <i class="fa-solid fa-signal"></i>
                    {{ \Illuminate\Support\Carbon::parse($today)->format('d M Y') }}
                </div>
                <h1 class="ops-title">Operations Dashboard</h1>
                <div class="ops-subtitle">
                    Pantau alur resi, QC, scan out, transaksi stok, dan risiko stok dari satu tampilan operasional.
                </div>
            </div>
            <form method="get" class="ops-date-form">
                <input type="text" name="date" id="filter_date" class="form-control form-control-solid" value="{{ $today }}" autocomplete="off">
                <button class="btn btn-light-primary" type="submit">
                    <i class="fa-solid fa-check me-2"></i>Apply
                </button>
            </form>
        </div>
    </section>

    <section class="ops-grid">
        <div class="ops-metric" style="--accent: var(--ops-blue);">
            <div class="ops-metric-head">
                <div>
                    <div class="ops-label">Total Resi Aktif</div>
                    <div class="ops-value">{{ number_format($summary['total_resi']) }}</div>
                </div>
                <div class="ops-icon"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <div class="ops-meta">Canceled: {{ number_format($summary['canceled_resi']) }}</div>
            <div class="ops-progress" style="--value: 100%;"><span></span></div>
        </div>

        <div class="ops-metric" style="--accent: var(--ops-violet);">
            <div class="ops-metric-head">
                <div>
                    <div class="ops-label">QC Scanned</div>
                    <div class="ops-value">{{ number_format($summary['qc_scanned']) }}</div>
                </div>
                <div class="ops-icon"><i class="fa-solid fa-barcode"></i></div>
            </div>
            <div class="ops-meta">{{ $summary['qc_rate'] }}% dari resi aktif, completed {{ number_format($summary['qc_completed']) }}</div>
            <div class="ops-progress" style="--value: {{ $qcRate }}%;"><span></span></div>
        </div>

        <div class="ops-metric" style="--accent: var(--ops-teal);">
            <div class="ops-metric-head">
                <div>
                    <div class="ops-label">Scan Out</div>
                    <div class="ops-value">{{ number_format($summary['scan_out']) }}</div>
                </div>
                <div class="ops-icon"><i class="fa-solid fa-truck-ramp-box"></i></div>
            </div>
            <div class="ops-meta">{{ $summary['scan_out_rate'] }}% selesai</div>
            <div class="ops-progress" style="--value: {{ $scanRate }}%;"><span></span></div>
        </div>

        <div class="ops-metric" style="--accent: var(--ops-red);">
            <div class="ops-metric-head">
                <div>
                    <div class="ops-label">Remaining Scan Out</div>
                    <div class="ops-value">{{ number_format($summary['remaining_scan_out']) }}</div>
                </div>
                <div class="ops-icon"><i class="fa-solid fa-clock"></i></div>
            </div>
            <div class="ops-meta">{{ $pendingRate }}% belum selesai</div>
            <div class="ops-progress" style="--value: {{ $pendingRate }}%;"><span></span></div>
        </div>
    </section>

    <div class="row g-6 mb-6">
        <div class="col-lg-6">
            <section class="ops-panel">
                <div class="ops-panel-head">
                    <div>
                        <h2 class="ops-panel-title">Transaksi Hari Ini</h2>
                        <div class="ops-panel-subtitle">Aktivitas inbound dan outbound berdasarkan tanggal transaksi.</div>
                    </div>
                    <span class="badge badge-light-primary">Live</span>
                </div>
                <div class="ops-tile-grid">
                    <div class="ops-tile">
                        <div class="ops-label">Inbound Receipt</div>
                        <div class="ops-tile-value">{{ number_format($summary['inbound_receipt']) }}</div>
                    </div>
                    <div class="ops-tile">
                        <div class="ops-label">Inbound Return</div>
                        <div class="ops-tile-value">{{ number_format($summary['inbound_return']) }}</div>
                    </div>
                    <div class="ops-tile">
                        <div class="ops-label">Outbound Manual</div>
                        <div class="ops-tile-value">{{ number_format($summary['outbound_manual']) }}</div>
                    </div>
                    <div class="ops-tile">
                        <div class="ops-label">Outbound Return</div>
                        <div class="ops-tile-value">{{ number_format($summary['outbound_return']) }}</div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="ops-panel">
                <div class="ops-panel-head">
                    <div>
                        <h2 class="ops-panel-title">Risiko Stok</h2>
                        <div class="ops-panel-subtitle">SKU yang perlu diprioritaskan untuk restock atau pengecekan.</div>
                    </div>
                    <span class="badge {{ $riskTotal > 0 ? 'badge-light-danger' : 'badge-light-success' }}">
                        {{ $riskTotal > 0 ? 'Action Needed' : 'Stable' }}
                    </span>
                </div>
                <div class="ops-risk-strip">
                    <div class="ops-risk" style="--accent: var(--ops-red);">
                        <div class="ops-label">Out of Stock</div>
                        <div class="ops-value">{{ number_format($summary['out_of_stock']) }}</div>
                        <div class="ops-meta">Stok kosong</div>
                    </div>
                    <div class="ops-risk" style="--accent: var(--ops-amber);">
                        <div class="ops-label">Low Stock</div>
                        <div class="ops-value">{{ number_format($summary['low_stock']) }}</div>
                        <div class="ops-meta">Di bawah safety stock</div>
                    </div>
                </div>
                <div class="px-6 pb-6 pt-4 text-muted fs-7">
                    Buka Stock Health Report untuk melihat gap SKU, safety stock, dan mutasi terakhir.
                </div>
            </section>
        </div>
    </div>

    <section class="ops-table-card">
        <div class="ops-panel-head">
            <div>
                <h2 class="ops-panel-title">Bottleneck Per Kurir</h2>
                <div class="ops-panel-subtitle">Diurutkan berdasarkan sisa scan out terbesar.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4 mb-0">
                <thead>
                    <tr class="text-start text-gray-500 fw-bolder fs-7 text-uppercase">
                        <th>Kurir</th>
                        <th class="text-end">Resi</th>
                        <th class="text-end">Scan Out</th>
                        <th class="text-end">Remaining</th>
                        <th>Completion</th>
                        <th>Last Scan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($couriers as $row)
                        @php
                            $initial = strtoupper(substr($row['name'] ?? '-', 0, 1));
                            $completion = min(100, max(0, (float) ($row['completion_rate'] ?? 0)));
                        @endphp
                        <tr>
                            <td>
                                <div class="ops-courier">
                                    <span class="ops-avatar">{{ $initial }}</span>
                                    <div>
                                        <div class="fw-bolder text-gray-900">{{ $row['name'] }}</div>
                                        <div class="text-muted fs-8">Canceled {{ number_format($row['canceled_total']) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end fw-bold">{{ number_format($row['total_resi']) }}</td>
                            <td class="text-end text-success fw-bold">{{ number_format($row['scan_total']) }}</td>
                            <td class="text-end fw-bolder {{ $row['remaining'] > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($row['remaining']) }}</td>
                            <td>
                                <div class="ops-mini-progress">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-bold">{{ $row['completion_rate'] }}%</span>
                                    </div>
                                    <div class="ops-progress" style="--accent: {{ $completion >= 90 ? 'var(--ops-teal)' : ($completion >= 60 ? 'var(--ops-amber)' : 'var(--ops-red)') }}; --value: {{ $completion }}%;">
                                        <span></span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted">{{ $row['last_scan_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-10">Tidak ada data operasional pada tanggal ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
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
