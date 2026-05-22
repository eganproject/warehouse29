@extends('layouts.admin')

@section('title', 'Laporan QC Scan')
@section('page_title', 'Laporan QC Scan')

@section('page_actions')
    <button type="button" class="btn btn-sm btn-light-primary" id="btn_print_report">
        <i class="bi bi-printer me-1"></i>Cetak Laporan
    </button>
@endsection

@section('content')
<style>
    .pr-report {
        --pr-blue: #1d4ed8;
        --pr-green: #047857;
        --pr-amber: #b45309;
        --pr-indigo: #4338ca;
        --pr-ink: #0f172a;
    }

    /* ---------- Filter panel ---------- */
    .pr-filter-card .form-control,
    .pr-filter-card .form-select { border-radius: 9px; }
    .pr-filter-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 12px;
    }
    .pr-field { display: flex; flex-direction: column; gap: 5px; }
    .pr-field-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .pr-col-3 { grid-column: span 3; }
    .pr-col-4 { grid-column: span 4; }
    .pr-col-12 { grid-column: span 12; }
    @media (max-width: 991px) { .pr-col-3, .pr-col-4 { grid-column: span 6; } }
    @media (max-width: 575px) { .pr-col-3, .pr-col-4 { grid-column: span 12; } }
    .pr-preset-group { display: flex; flex-wrap: wrap; gap: 6px; }
    .pr-preset {
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        font-size: 11.5px;
        font-weight: 600;
        padding: 5px 11px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .pr-preset:hover { border-color: var(--pr-blue); color: var(--pr-blue); }
    .pr-preset.active { background: var(--pr-blue); border-color: var(--pr-blue); color: #fff; }

    /* ---------- Document ---------- */
    .pr-doc { border: 1px solid #e5e9f0; }
    .pr-doc-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--pr-ink);
    }
    .pr-brand { display: flex; align-items: center; gap: 12px; }
    .pr-brand-logo {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, #1d4ed8, #4338ca);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    .pr-brand-name {
        font-size: 18px;
        font-weight: 800;
        color: var(--pr-ink);
        line-height: 1.2;
        letter-spacing: -0.01em;
    }
    .pr-brand-sub { font-size: 11.5px; color: #64748b; }
    .pr-doc-meta { text-align: right; font-size: 11.5px; color: #64748b; line-height: 1.7; }
    .pr-doc-meta b { color: var(--pr-ink); }
    .pr-doc-titlebar { text-align: center; margin: 18px 0 6px; }
    .pr-doc-title {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: 0.08em;
        color: var(--pr-ink);
        margin: 0;
    }
    .pr-doc-period { font-size: 12px; color: #475569; margin-top: 4px; }
    .pr-doc-period .sep { color: #cbd5e1; margin: 0 6px; }
    .pr-active-view { display: none; }

    /* ---------- Summary tiles ---------- */
    .pr-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin: 18px 0;
    }
    @media (max-width: 767px) { .pr-summary-grid { grid-template-columns: repeat(2, 1fr); } }
    .pr-tile {
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid #e9edf3;
        border-radius: 12px;
        padding: 13px 14px;
        background: #fff;
    }
    .pr-tile-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        color: var(--tile, var(--pr-blue));
        background: var(--tile-soft, rgba(29, 78, 216, 0.1));
    }
    .pr-tile-label {
        font-size: 10.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .pr-tile-value { font-size: 22px; font-weight: 800; color: var(--pr-ink); line-height: 1.15; }
    .pr-tile-sub { font-size: 10.5px; color: #94a3b8; font-weight: 600; }
    .pr-tile--blue   { --tile: var(--pr-blue);   --tile-soft: rgba(29, 78, 216, 0.1); }
    .pr-tile--green  { --tile: var(--pr-green);  --tile-soft: rgba(4, 120, 87, 0.1); }
    .pr-tile--amber  { --tile: var(--pr-amber);  --tile-soft: rgba(180, 83, 9, 0.12); }
    .pr-tile--indigo { --tile: var(--pr-indigo); --tile-soft: rgba(67, 56, 202, 0.1); }

    /* ---------- Tabs & table ---------- */
    .pr-tabs { border-bottom: 1px solid #e2e8f0; margin-bottom: 16px; }
    .pr-tabs .nav-link {
        font-weight: 700;
        font-size: 13px;
        color: #64748b;
        border: none;
        padding: 10px 16px;
    }
    .pr-tabs .nav-link.active {
        color: var(--pr-blue);
        border-bottom: 2.5px solid var(--pr-blue);
        background: transparent;
    }
    #picker_reports_table thead th,
    #picker_sku_table thead th {
        background: #f1f5f9;
        color: #475569;
        font-size: 10.5px;
        white-space: nowrap;
        border-bottom: 1.5px solid #e2e8f0 !important;
    }
    #picker_reports_table tbody td,
    #picker_sku_table tbody td { vertical-align: middle; }
    .pr-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .pr-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: #eef2f7;
        color: #475569;
    }
    .pr-chip--blue  { background: rgba(29, 78, 216, 0.1);  color: #1d4ed8; }
    .pr-chip--green { background: rgba(4, 120, 87, 0.12);  color: #047857; }

    /* ---------- Signature (print only) ---------- */
    .pr-doc-sign { display: none; }

    /* ---------- Print ---------- */
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        #kt_header, #kt_toolbar, #kt_footer,
        .pr-no-print, .pr-tabs,
        .dataTables_paginate, .dataTables_length, .dataTables_processing { display: none !important; }
        body { background: #fff !important; }
        #kt_content_container, .content { padding: 0 !important; margin: 0 !important; }
        .pr-doc { border: none !important; box-shadow: none !important; }
        .pr-doc .card-body { padding: 0 !important; }
        .pr-active-view { display: block !important; }
        .pr-tile { break-inside: avoid; }
        .tab-pane { display: block !important; opacity: 1 !important; }
        .tab-pane:not(.active) { display: none !important; }
        #picker_reports_table, #picker_sku_table { font-size: 9.5px; }
        #picker_reports_table thead th,
        #picker_sku_table thead th { background: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        #picker_reports_table th, #picker_reports_table td,
        #picker_sku_table th, #picker_sku_table td { border: 0.5px solid #cbd5e1 !important; }
        .pr-tile-icon, .pr-brand-logo, .pr-chip {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .pr-doc-sign {
            display: flex !important;
            justify-content: space-between;
            gap: 40px;
            margin-top: 44px;
            page-break-inside: avoid;
        }
        .pr-sign-box { text-align: center; font-size: 12px; width: 220px; }
        .pr-sign-space { height: 64px; }
        .pr-sign-line { border-top: 1px solid #0f172a; padding-top: 4px; font-weight: 700; }
        .dataTables_info { font-size: 11px; color: #475569; margin-top: 8px; }
    }
</style>

<div class="pr-report">

    {{-- ============ Filter Panel (tidak ikut tercetak) ============ --}}
    <div class="card mb-5 pr-no-print pr-filter-card">
        <div class="card-body py-5">
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-funnel-fill text-primary fs-4"></i>
                <span class="fw-bold fs-5 text-gray-800">Filter Laporan</span>
            </div>

            <div class="pr-filter-grid">
                <div class="pr-field pr-col-4">
                    <span class="pr-field-label">Cari Petugas QC</span>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute ms-3 text-gray-500 fs-6" style="top:50%;transform:translateY(-50%)"></i>
                        <input type="text" class="form-control form-control-solid ps-10" id="filter_search_picker" placeholder="Nama petugas QC" autocomplete="off" />
                    </div>
                </div>
                <div class="pr-field pr-col-4">
                    <span class="pr-field-label">Divisi</span>
                    <select id="filter_divisi" class="form-select form-select-solid">
                        <option value="">Semua Divisi</option>
                        @foreach($divisis as $divisi)
                            <option value="{{ $divisi->id }}">{{ $divisi->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pr-field pr-col-4">
                    <span class="pr-field-label">Data per Halaman</span>
                    <select id="filter_limit" class="form-select form-select-solid">
                        <option value="10" selected>10 baris</option>
                        <option value="20">20 baris</option>
                        <option value="50">50 baris</option>
                        <option value="100">100 baris</option>
                        <option value="500">500 baris</option>
                    </select>
                </div>
                <div class="pr-field pr-col-3">
                    <span class="pr-field-label">Tanggal Dari</span>
                    <input type="text" class="form-control form-control-solid" id="filter_date_from" placeholder="Semua tanggal" autocomplete="off" />
                </div>
                <div class="pr-field pr-col-3">
                    <span class="pr-field-label">Tanggal Sampai</span>
                    <input type="text" class="form-control form-control-solid" id="filter_date_to" placeholder="Semua tanggal" autocomplete="off" />
                </div>
                <div class="pr-field pr-col-12">
                    <div class="pr-preset-group" id="pr_preset_group">
                        <span class="pr-field-label me-1 align-self-center">Rentang Cepat:</span>
                        <button type="button" class="pr-preset active" data-range="all">Semua</button>
                        <button type="button" class="pr-preset" data-range="today">Hari Ini</button>
                        <button type="button" class="pr-preset" data-range="yesterday">Kemarin</button>
                        <button type="button" class="pr-preset" data-range="7d">7 Hari</button>
                        <button type="button" class="pr-preset" data-range="30d">30 Hari</button>
                        <button type="button" class="pr-preset" data-range="month">Bulan Ini</button>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="button" class="btn btn-primary" id="filter_apply">
                    <i class="bi bi-funnel me-1"></i>Terapkan Filter
                </button>
                <button type="button" class="btn btn-light" id="filter_reset">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ============ Dokumen Laporan ============ --}}
    <div class="card pr-doc">
        <div class="card-body p-8">

            {{-- Kop dokumen --}}
            <div class="pr-doc-head">
                <div class="pr-brand">
                    <div class="pr-brand-logo"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <div class="pr-brand-name">{{ config('app.name', 'Warehouse 29') }}</div>
                        <div class="pr-brand-sub">Sistem Manajemen Gudang &mdash; Laporan Operasional</div>
                    </div>
                </div>
                <div class="pr-doc-meta">
                    <div>Dicetak: <b id="pr_printed_at">-</b></div>
                    <div>Oleh: <b>{{ $generatedBy ?? '-' }}</b></div>
                </div>
            </div>

            <div class="pr-doc-titlebar">
                <h2 class="pr-doc-title">LAPORAN QC SCAN</h2>
                <div class="pr-doc-period" id="pr_period_text">Periode: Semua Tanggal</div>
                <div class="pr-active-view" id="pr_active_view">Bagian: Ringkasan QC per Petugas</div>
            </div>

            {{-- Ringkasan --}}
            <div class="pr-summary-grid">
                <div class="pr-tile pr-tile--blue">
                    <div class="pr-tile-icon"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="pr-tile-label">Petugas QC</div>
                        <div class="pr-tile-value" id="sum_picker">0</div>
                        <div class="pr-tile-sub" id="sum_days">0 hari aktif</div>
                    </div>
                </div>
                <div class="pr-tile pr-tile--green">
                    <div class="pr-tile-icon"><i class="bi bi-collection"></i></div>
                    <div>
                        <div class="pr-tile-label">Total Batch QC</div>
                        <div class="pr-tile-value" id="sum_batch">0</div>
                        <div class="pr-tile-sub">batch discan</div>
                    </div>
                </div>
                <div class="pr-tile pr-tile--amber">
                    <div class="pr-tile-icon"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <div class="pr-tile-label">Total Qty Discan</div>
                        <div class="pr-tile-value" id="sum_qty">0</div>
                        <div class="pr-tile-sub" id="sum_sku">0 baris SKU</div>
                    </div>
                </div>
                <div class="pr-tile pr-tile--indigo">
                    <div class="pr-tile-icon"><i class="bi bi-speedometer2"></i></div>
                    <div>
                        <div class="pr-tile-label">Produktivitas</div>
                        <div class="pr-tile-value" id="sum_productivity">0</div>
                        <div class="pr-tile-sub">qty / jam rata-rata</div>
                    </div>
                </div>
            </div>

            {{-- Tabs --}}
            <ul class="nav nav-tabs pr-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tab_report_picker" role="tab">
                        <i class="bi bi-person-badge me-1"></i>Ringkasan QC per Petugas
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" data-bs-toggle="tab" href="#tab_report_sku" role="tab">
                        <i class="bi bi-upc-scan me-1"></i>Ringkasan per SKU
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                {{-- Tab 1: per petugas --}}
                <div class="tab-pane fade show active" id="tab_report_picker" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-bordered fs-7 gy-3" id="picker_reports_table">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bolder text-uppercase gs-0">
                                    <th class="text-center" width="44">No</th>
                                    <th>Tanggal</th>
                                    <th>Petugas QC</th>
                                    <th class="text-center">Batch</th>
                                    <th class="text-center">SKU</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Rata2 Qty/Batch</th>
                                    <th class="text-center">Rata2 SKU/Batch</th>
                                    <th class="text-center">Rata2 Durasi</th>
                                    <th class="text-center">Total Durasi</th>
                                    <th>Produktivitas</th>
                                    <th>Jam Kerja</th>
                                    <th class="text-end pr-no-print" width="80">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- Tab 2: per SKU --}}
                <div class="tab-pane fade" id="tab_report_sku" role="tabpanel">
                    <div class="d-flex justify-content-end mb-3 pr-no-print">
                        <div class="position-relative" style="max-width:280px">
                            <i class="bi bi-search position-absolute ms-3 text-gray-500 fs-6" style="top:50%;transform:translateY(-50%)"></i>
                            <input type="text" class="form-control form-control-solid ps-10" id="filter_search_sku" placeholder="Cari SKU / nama item" autocomplete="off" />
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-bordered fs-7 gy-3" id="picker_sku_table">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bolder text-uppercase gs-0">
                                    <th class="text-center" width="44">No</th>
                                    <th>SKU</th>
                                    <th>Nama Item</th>
                                    <th class="text-center">Total Qty</th>
                                    <th class="text-center">Jumlah Batch</th>
                                    <th class="text-center">Petugas QC</th>
                                    <th class="text-center">Rata2 Qty/Batch</th>
                                    <th>Rincian Petugas QC (Qty)</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Tanda tangan (hanya tampil saat dicetak) --}}
            <div class="pr-doc-sign">
                <div class="pr-sign-box">
                    <div>Disiapkan oleh,</div>
                    <div class="pr-sign-space"></div>
                    <div class="pr-sign-line">{{ $generatedBy ?? '( ............................ )' }}</div>
                    <div>Admin Gudang</div>
                </div>
                <div class="pr-sign-box">
                    <div>Mengetahui,</div>
                    <div class="pr-sign-space"></div>
                    <div class="pr-sign-line">( ............................ )</div>
                    <div>Supervisor</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============ Modal Detail ============ --}}
<div class="modal fade" id="modal_report_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-1">
                        <i class="bi bi-person-badge text-primary me-2"></i>Detail QC Scan Petugas
                    </h5>
                    <div class="text-muted fs-7" id="detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="pr-summary-grid mb-5" style="grid-template-columns:repeat(3,1fr)">
                    <div class="pr-tile pr-tile--blue">
                        <div class="pr-tile-icon"><i class="bi bi-collection"></i></div>
                        <div><div class="pr-tile-label">Batch</div><div class="pr-tile-value fs-3" id="detail_batch">-</div></div>
                    </div>
                    <div class="pr-tile pr-tile--green">
                        <div class="pr-tile-icon"><i class="bi bi-upc-scan"></i></div>
                        <div><div class="pr-tile-label">SKU</div><div class="pr-tile-value fs-3" id="detail_sku">-</div></div>
                    </div>
                    <div class="pr-tile pr-tile--amber">
                        <div class="pr-tile-icon"><i class="bi bi-box-seam"></i></div>
                        <div><div class="pr-tile-label">Total Qty</div><div class="pr-tile-value fs-3" id="detail_qty">-</div></div>
                    </div>
                </div>
                <div class="row g-3 mb-5">
                    <div class="col-6 col-md-3"><div class="text-muted fs-8">Rata2 Qty/Batch</div><div class="fw-bold" id="detail_avg_qty">-</div></div>
                    <div class="col-6 col-md-3"><div class="text-muted fs-8">Rata2 SKU/Batch</div><div class="fw-bold" id="detail_avg_sku">-</div></div>
                    <div class="col-6 col-md-3"><div class="text-muted fs-8">Rata2 Durasi</div><div class="fw-bold" id="detail_avg_duration">-</div></div>
                    <div class="col-6 col-md-3"><div class="text-muted fs-8">Total Durasi</div><div class="fw-bold" id="detail_total_duration">-</div></div>
                    <div class="col-6 col-md-3"><div class="text-muted fs-8">Produktivitas</div><div class="fw-bold" id="detail_productivity">-</div></div>
                    <div class="col-6 col-md-3"><div class="text-muted fs-8">Jam Kerja</div><div class="fw-bold" id="detail_range">-</div></div>
                    <div class="col-6 col-md-3"><div class="text-muted fs-8">Tanggal</div><div class="fw-bold" id="detail_date">-</div></div>
                    <div class="col-6 col-md-3"><div class="text-muted fs-8">Petugas QC</div><div class="fw-bold" id="detail_picker">-</div></div>
                </div>
                <h6 class="fw-bold text-gray-700 mb-3"><i class="bi bi-list-ul me-1 text-primary"></i>Rincian Item</h6>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="8%">No</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="detail_items">
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl   = '{{ $dataUrl }}';
    const skuUrl    = '{{ route('admin.outbound.picker-reports.sku') }}';
    const detailUrl = '{{ route('admin.outbound.picker-reports.detail') }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picker_reports_table');
        const skuTableEl = $('#picker_sku_table');
        const searchPicker = document.getElementById('filter_search_picker');
        const searchSku = document.getElementById('filter_search_sku');
        const divisiSelect = document.getElementById('filter_divisi');
        const limitSelect = document.getElementById('filter_limit');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const printBtn = document.getElementById('btn_print_report');
        const presetGroup = document.getElementById('pr_preset_group');
        const periodText = document.getElementById('pr_period_text');
        const activeViewEl = document.getElementById('pr_active_view');
        const printedAtEl = document.getElementById('pr_printed_at');
        const detailModalEl = document.getElementById('modal_report_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;

        const ID_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const esc = v => String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        const num = v => Number(v || 0).toLocaleString('id-ID');
        const pad = n => String(n).padStart(2, '0');
        const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value ?? '-'; };

        const formatDateID = (ymd) => {
            if (!ymd) return '';
            const p = String(ymd).split('-');
            if (p.length !== 3) return ymd;
            return `${p[2]} ${ID_MONTHS[Number(p[1]) - 1] || p[1]} ${p[0]}`;
        };

        const nowDate = new Date();
        if (printedAtEl) {
            printedAtEl.textContent = `${pad(nowDate.getDate())} ${ID_MONTHS[nowDate.getMonth()]} ${nowDate.getFullYear()}, ${pad(nowDate.getHours())}:${pad(nowDate.getMinutes())}`;
        }

        // ---------- Select2 ----------
        if (divisiSelect && $.fn.select2) {
            $(divisiSelect).select2({ placeholder: 'Semua Divisi', allowClear: true, width: '100%' })
                .on('select2:opening select2:closing select2:close', e => e.stopPropagation());
        }

        // ---------- Flatpickr ----------
        let fpFrom = null, fpTo = null;
        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            if (dateToEl) fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
        }
        const setDate = (fp, el, value) => {
            if (fp) fp.setDate(value || null, true);
            else if (el) el.value = value || '';
        };

        // ---------- Date presets ----------
        const toYMD = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        const computeRange = (range) => {
            const today = new Date();
            const from = new Date(today);
            switch (range) {
                case 'today':     return [toYMD(today), toYMD(today)];
                case 'yesterday': from.setDate(from.getDate() - 1); return [toYMD(from), toYMD(from)];
                case '7d':        from.setDate(from.getDate() - 6); return [toYMD(from), toYMD(today)];
                case '30d':       from.setDate(from.getDate() - 29); return [toYMD(from), toYMD(today)];
                case 'month':     return [toYMD(new Date(today.getFullYear(), today.getMonth(), 1)), toYMD(today)];
                default:          return ['', ''];
            }
        };
        const markPreset = (range) => {
            presetGroup?.querySelectorAll('.pr-preset').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-range') === range);
            });
        };
        presetGroup?.querySelectorAll('.pr-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                const range = btn.getAttribute('data-range');
                const [from, to] = computeRange(range);
                setDate(fpFrom, dateFromEl, from);
                setDate(fpTo, dateToEl, to);
                markPreset(range);
                reloadAll();
            });
        });
        [dateFromEl, dateToEl].forEach(el => el?.addEventListener('change', () => markPreset(null)));

        // ---------- Period & summary ----------
        const updatePeriodText = () => {
            const from = dateFromEl?.value || '';
            const to = dateToEl?.value || '';
            let period = 'Semua Tanggal';
            if (from && to) period = `${formatDateID(from)} s.d. ${formatDateID(to)}`;
            else if (from) period = `Sejak ${formatDateID(from)}`;
            else if (to) period = `Hingga ${formatDateID(to)}`;

            const divisiName = divisiSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Semua Divisi';
            if (periodText) {
                periodText.innerHTML =
                    `Periode: <b>${esc(period)}</b><span class="sep">|</span>Divisi: <b>${esc(divisiName)}</b>`;
            }
        };

        const updateSummary = (s) => {
            if (!s) return;
            setText('sum_picker', num(s.picker_count));
            setText('sum_days', `${num(s.day_count)} hari aktif`);
            setText('sum_batch', num(s.batch_total));
            setText('sum_qty', num(s.qty_total));
            setText('sum_sku', `${num(s.sku_total)} baris SKU`);
            setText('sum_productivity', num(s.productivity));
        };

        // ---------- DataTables ----------
        const langID = {
            processing: 'Memuat data...',
            emptyTable: 'Belum ada data laporan QC scan.',
            zeroRecords: 'Tidak ada data yang cocok dengan filter.',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ baris',
            infoEmpty: 'Menampilkan 0 baris',
            infoFiltered: '(disaring dari _MAX_ total)',
            paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
        };

        const buildAjax = (url, getSearch, withSummary) => ({
            url,
            data(d) {
                d.q = getSearch() || '';
                d.divisi_id = divisiSelect?.value || '';
                d.date_from = dateFromEl?.value || '';
                d.date_to = dateToEl?.value || '';
            },
            dataSrc(json) {
                if (withSummary && json.summary) {
                    updateSummary(json.summary);
                    updatePeriodText();
                }
                return json.data || [];
            },
        });

        const rowNo = (data, type, row, meta) => meta.settings._iDisplayStart + meta.row + 1;

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            ordering: false,
            pageLength: Number(limitSelect?.value || 10),
            language: langID,
            ajax: buildAjax(dataUrl, () => searchPicker?.value || '', true),
            columns: [
                { data: null, className: 'text-center text-muted fw-semibold', render: rowNo },
                { data: 'date', render: d => `<span class="fw-bold">${esc(formatDateID(d)) || '-'}</span>` },
                { data: 'picker', render: d => `<span class="fw-semibold text-gray-800">${esc(d) || '-'}</span>` },
                { data: 'batch_count', className: 'text-center', render: d => `<span class="pr-chip pr-chip--blue">${num(d)}</span>` },
                { data: 'sku_count', className: 'text-center pr-num', render: num },
                { data: 'qty', className: 'text-center pr-num', render: d => `<span class="pr-chip pr-chip--green">${num(d)}</span>` },
                { data: 'avg_qty', className: 'text-center pr-num', render: num },
                { data: 'avg_sku', className: 'text-center pr-num', render: num },
                { data: 'avg_duration', className: 'text-center' },
                { data: 'total_duration', className: 'text-center' },
                { data: 'productivity', render: d => `<span class="fw-bold text-primary">${esc(d) || '-'}</span>` },
                { data: 'range', render: d => `<span class="text-muted"><i class="bi bi-clock me-1"></i>${esc(d) || '-'}</span>` },
                {
                    data: null,
                    orderable: false,
                    className: 'text-end pr-no-print',
                    render: (data, type, row) =>
                        `<button type="button" class="btn btn-sm btn-light-primary btn-detail px-3 py-1" data-date="${esc(row.date)}" data-user="${esc(String(row.user_id))}">
                            <i class="bi bi-eye"></i>
                        </button>`,
                },
            ],
        });

        const dtSku = skuTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            ordering: false,
            pageLength: Number(limitSelect?.value || 10),
            language: langID,
            ajax: buildAjax(skuUrl, () => searchSku?.value || '', false),
            columns: [
                { data: null, className: 'text-center text-muted fw-semibold', render: rowNo },
                { data: 'sku', render: d => `<span class="fw-bold font-monospace text-primary">${esc(d) || '-'}</span>` },
                { data: 'name', render: d => esc(d) || '-' },
                { data: 'total_qty', className: 'text-center pr-num', render: d => `<span class="pr-chip pr-chip--green">${num(d)}</span>` },
                { data: 'batch_count', className: 'text-center pr-num', render: num },
                { data: 'picker_count', className: 'text-center pr-num', render: num },
                { data: 'avg_qty', className: 'text-center pr-num', render: num },
                { data: 'picker_list', render: d => `<span class="text-muted fs-8">${esc(d) || '-'}</span>` },
            ],
        });

        const reloadAll = () => { dt.ajax.reload(); dtSku.ajax.reload(); };

        // ---------- Tab handling ----------
        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                dt.columns.adjust();
                dtSku.columns.adjust();
                if (activeViewEl) {
                    activeViewEl.textContent = e.target.getAttribute('href') === '#tab_report_sku'
                        ? 'Bagian: Ringkasan per SKU'
                        : 'Bagian: Ringkasan QC per Petugas';
                }
            });
        });

        // ---------- Filter events ----------
        let pickerTimer = null, skuTimer = null;
        searchPicker?.addEventListener('input', () => {
            clearTimeout(pickerTimer);
            pickerTimer = setTimeout(() => dt.ajax.reload(), 400);
        });
        searchSku?.addEventListener('input', () => {
            clearTimeout(skuTimer);
            skuTimer = setTimeout(() => dtSku.ajax.reload(), 400);
        });
        applyBtn?.addEventListener('click', reloadAll);
        $(divisiSelect).on('change', reloadAll);
        limitSelect?.addEventListener('change', () => {
            const len = Number(limitSelect.value || 10);
            dt.page.len(len).draw();
            dtSku.page.len(len).draw();
        });
        resetBtn?.addEventListener('click', () => {
            if (searchPicker) searchPicker.value = '';
            if (searchSku) searchSku.value = '';
            if (divisiSelect) {
                divisiSelect.value = '';
                if ($(divisiSelect).data('select2')) $(divisiSelect).val('').trigger('change.select2');
            }
            if (limitSelect) { limitSelect.value = '10'; dt.page.len(10); dtSku.page.len(10); }
            setDate(fpFrom, dateFromEl, '');
            setDate(fpTo, dateToEl, '');
            markPreset('all');
            reloadAll();
        });

        printBtn?.addEventListener('click', () => window.print());

        // ---------- Detail modal ----------
        tableEl.on('click', '.btn-detail', async function(e) {
            e.preventDefault();
            const date = this.getAttribute('data-date');
            const userId = this.getAttribute('data-user');
            if (!date || !userId) return;
            try {
                const url = `${detailUrl}?date=${encodeURIComponent(date)}&user_id=${encodeURIComponent(userId)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat detail', 'error');
                    return;
                }
                setText('detail_subtitle', `${formatDateID(json.date)} • ${json.picker || '-'}`);
                setText('detail_date', formatDateID(json.date));
                setText('detail_picker', json.picker);
                setText('detail_batch', num(json.batch_count));
                setText('detail_sku', num(json.sku_count));
                setText('detail_qty', num(json.qty));
                setText('detail_avg_qty', json.avg_qty ?? '-');
                setText('detail_avg_sku', json.avg_sku ?? '-');
                setText('detail_avg_duration', json.avg_duration ?? '-');
                setText('detail_total_duration', json.total_duration ?? '-');
                setText('detail_productivity', json.productivity ?? '-');
                setText('detail_range', `${json.first_started_at || '-'} - ${json.last_submitted_at || '-'}`);

                const items = json.items || [];
                const tbody = document.getElementById('detail_items');
                if (tbody) {
                    tbody.innerHTML = items.length
                        ? items.map((row, i) => `
                            <tr>
                                <td class="text-muted">${i + 1}</td>
                                <td><span class="fw-bold font-monospace">${esc(row.sku) || '-'}</span></td>
                                <td>${esc(row.name) || '-'}</td>
                                <td class="text-end fw-semibold">${num(row.qty)}</td>
                            </tr>`).join('')
                        : '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada item.</td></tr>';
                }
                detailModal?.show();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal memuat detail', 'error');
            }
        });

        updatePeriodText();
    });
</script>
@endpush
