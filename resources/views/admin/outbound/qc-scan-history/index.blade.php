@extends('layouts.admin')

@section('title', 'History QC Scan')
@section('page_title', 'History QC Scan')

@section('page_actions')
    <button type="button" class="btn btn-sm btn-light-primary" id="btn_print_report">
        <i class="bi bi-printer me-1"></i>Cetak Laporan
    </button>
@endsection

@section('content')
<style>
    .qc-report {
        --qc-blue: #1d4ed8;
        --qc-green: #047857;
        --qc-amber: #b45309;
        --qc-indigo: #4338ca;
        --qc-ink: #0f172a;
    }

    /* ---------- Filter panel ---------- */
    .qc-filter-card .form-control,
    .qc-filter-card .form-select { border-radius: 9px; }
    .qc-filter-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 12px;
    }
    .qc-field { display: flex; flex-direction: column; gap: 5px; }
    .qc-field-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .qc-col-3 { grid-column: span 3; }
    .qc-col-4 { grid-column: span 4; }
    .qc-col-6 { grid-column: span 6; }
    .qc-col-12 { grid-column: span 12; }
    @media (max-width: 991px) {
        .qc-col-3, .qc-col-4 { grid-column: span 6; }
    }
    @media (max-width: 575px) {
        .qc-col-3, .qc-col-4, .qc-col-6 { grid-column: span 12; }
    }
    .qc-preset-group { display: flex; flex-wrap: wrap; gap: 6px; }
    .qc-preset {
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
    .qc-preset:hover { border-color: var(--qc-blue); color: var(--qc-blue); }
    .qc-preset.active {
        background: var(--qc-blue);
        border-color: var(--qc-blue);
        color: #fff;
    }

    /* ---------- Document ---------- */
    .qc-doc { border: 1px solid #e5e9f0; }
    .qc-doc-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--qc-ink);
    }
    .qc-brand { display: flex; align-items: center; gap: 12px; }
    .qc-brand-logo {
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
    .qc-brand-name {
        font-size: 18px;
        font-weight: 800;
        color: var(--qc-ink);
        line-height: 1.2;
        letter-spacing: -0.01em;
    }
    .qc-brand-sub { font-size: 11.5px; color: #64748b; }
    .qc-doc-meta {
        text-align: right;
        font-size: 11.5px;
        color: #64748b;
        line-height: 1.7;
    }
    .qc-doc-meta b { color: var(--qc-ink); }
    .qc-doc-titlebar { text-align: center; margin: 18px 0 6px; }
    .qc-doc-title {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: 0.08em;
        color: var(--qc-ink);
        margin: 0;
    }
    .qc-doc-period {
        font-size: 12px;
        color: #475569;
        margin-top: 4px;
    }
    .qc-doc-period .sep { color: #cbd5e1; margin: 0 6px; }

    /* ---------- Summary tiles ---------- */
    .qc-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin: 18px 0;
    }
    @media (max-width: 767px) { .qc-summary-grid { grid-template-columns: repeat(2, 1fr); } }
    .qc-tile {
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid #e9edf3;
        border-radius: 12px;
        padding: 13px 14px;
        background: #fff;
    }
    .qc-tile-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        color: var(--tile, var(--qc-blue));
        background: var(--tile-soft, rgba(29, 78, 216, 0.1));
    }
    .qc-tile-label {
        font-size: 10.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .qc-tile-value {
        font-size: 22px;
        font-weight: 800;
        color: var(--qc-ink);
        line-height: 1.15;
    }
    .qc-tile-sub { font-size: 10.5px; color: #94a3b8; font-weight: 600; }
    .qc-tile--blue   { --tile: var(--qc-blue);   --tile-soft: rgba(29, 78, 216, 0.1); }
    .qc-tile--green  { --tile: var(--qc-green);  --tile-soft: rgba(4, 120, 87, 0.1); }
    .qc-tile--amber  { --tile: var(--qc-amber);  --tile-soft: rgba(180, 83, 9, 0.12); }
    .qc-tile--indigo { --tile: var(--qc-indigo); --tile-soft: rgba(67, 56, 202, 0.1); }

    /* ---------- Table ---------- */
    #qc_scan_history_table thead th {
        background: #f1f5f9;
        color: #475569;
        font-size: 10.5px;
        border-bottom: 1.5px solid #e2e8f0 !important;
    }
    #qc_scan_history_table tbody td { vertical-align: middle; }
    .qc-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .qc-status-badge--done { background: rgba(4, 120, 87, 0.12); color: #047857; }
    .qc-status-badge--wip  { background: rgba(180, 83, 9, 0.12); color: #b45309; }

    /* ---------- Signature (print only) ---------- */
    .qc-doc-sign { display: none; }

    /* ---------- Print ---------- */
    @media print {
        @page { size: A4 landscape; margin: 12mm; }
        #kt_header, #kt_toolbar, #kt_footer,
        .qc-no-print,
        .dataTables_paginate, .dataTables_length, .dataTables_processing { display: none !important; }
        body { background: #fff !important; }
        #kt_content_container, .content { padding: 0 !important; margin: 0 !important; }
        .qc-doc {
            border: none !important;
            box-shadow: none !important;
        }
        .qc-doc .card-body { padding: 0 !important; }
        .qc-tile { break-inside: avoid; }
        #qc_scan_history_table thead th { background: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        #qc_scan_history_table th,
        #qc_scan_history_table td { border: 0.5px solid #cbd5e1 !important; }
        .qc-status-badge, .qc-tile-icon, .qc-brand-logo, .progress-bar {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .qc-doc-sign {
            display: flex !important;
            justify-content: space-between;
            gap: 40px;
            margin-top: 48px;
            page-break-inside: avoid;
        }
        .qc-sign-box { text-align: center; font-size: 12px; width: 220px; }
        .qc-sign-space { height: 70px; }
        .qc-sign-line { border-top: 1px solid #0f172a; padding-top: 4px; font-weight: 700; }
        .dataTables_info { font-size: 11px; color: #475569; margin-top: 8px; }
    }
</style>

<div class="qc-report">

    {{-- ============ Filter Panel (tidak ikut tercetak) ============ --}}
    <div class="card mb-5 qc-no-print qc-filter-card">
        <div class="card-body py-5">
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-funnel-fill text-primary fs-4"></i>
                <span class="fw-bold fs-5 text-gray-800">Filter Laporan</span>
            </div>

            <div class="qc-filter-grid">
                <div class="qc-field qc-col-4">
                    <span class="qc-field-label">Pencarian</span>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute ms-3 text-gray-500 fs-6" style="top:50%;transform:translateY(-50%)"></i>
                        <input type="text" class="form-control form-control-solid ps-10" id="qc_search" placeholder="No resi / ID pesanan / SKU / petugas" autocomplete="off" />
                    </div>
                </div>
                <div class="qc-field qc-col-3">
                    <span class="qc-field-label">Tanggal Dari</span>
                    <input type="text" class="form-control form-control-solid" id="filter_date_from" placeholder="Semua tanggal" autocomplete="off" />
                </div>
                <div class="qc-field qc-col-3">
                    <span class="qc-field-label">Tanggal Sampai</span>
                    <input type="text" class="form-control form-control-solid" id="filter_date_to" placeholder="Semua tanggal" autocomplete="off" />
                </div>
                <div class="qc-field qc-col-12">
                    <div class="qc-preset-group" id="qc_preset_group">
                        <span class="qc-field-label me-1 align-self-center">Rentang Cepat:</span>
                        <button type="button" class="qc-preset active" data-range="all">Semua</button>
                        <button type="button" class="qc-preset" data-range="today">Hari Ini</button>
                        <button type="button" class="qc-preset" data-range="yesterday">Kemarin</button>
                        <button type="button" class="qc-preset" data-range="7d">7 Hari</button>
                        <button type="button" class="qc-preset" data-range="30d">30 Hari</button>
                        <button type="button" class="qc-preset" data-range="month">Bulan Ini</button>
                    </div>
                </div>
                <div class="qc-field qc-col-4">
                    <span class="qc-field-label">Petugas QC</span>
                    <select id="filter_picker_user" class="form-select form-select-solid">
                        <option value="">Semua Petugas</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="qc-field qc-col-4">
                    <span class="qc-field-label">Status QC</span>
                    <select id="filter_picker_status" class="form-select form-select-solid">
                        <option value="">Semua Status</option>
                        <option value="in_progress">Belum Lengkap</option>
                        <option value="completed">Selesai</option>
                    </select>
                </div>
                <div class="qc-field qc-col-4">
                    <span class="qc-field-label">Data per Halaman</span>
                    <select id="filter_limit" class="form-select form-select-solid">
                        <option value="10" selected>10 baris</option>
                        <option value="20">20 baris</option>
                        <option value="50">50 baris</option>
                        <option value="100">100 baris</option>
                        <option value="500">500 baris</option>
                    </select>
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
    <div class="card qc-doc">
        <div class="card-body p-8">

            {{-- Kop dokumen --}}
            <div class="qc-doc-head">
                <div class="qc-brand">
                    <div class="qc-brand-logo"><i class="bi bi-clipboard2-check"></i></div>
                    <div>
                        <div class="qc-brand-name">{{ config('app.name', 'Warehouse 29') }}</div>
                        <div class="qc-brand-sub">Sistem Manajemen Gudang &mdash; Divisi Quality Control</div>
                    </div>
                </div>
                <div class="qc-doc-meta">
                    <div>Dicetak: <b id="qc_printed_at">-</b></div>
                    <div>Oleh: <b>{{ $generatedBy ?? '-' }}</b></div>
                </div>
            </div>

            <div class="qc-doc-titlebar">
                <h2 class="qc-doc-title">RIWAYAT QC SCAN</h2>
                <div class="qc-doc-period" id="qc_period_text">Periode: Semua Tanggal</div>
            </div>

            {{-- Ringkasan --}}
            <div class="qc-summary-grid">
                <div class="qc-tile qc-tile--blue">
                    <div class="qc-tile-icon"><i class="bi bi-clipboard-data"></i></div>
                    <div>
                        <div class="qc-tile-label">Total Resi QC</div>
                        <div class="qc-tile-value" id="sum_total_resi">0</div>
                        <div class="qc-tile-sub">resi diproses QC</div>
                    </div>
                </div>
                <div class="qc-tile qc-tile--green">
                    <div class="qc-tile-icon"><i class="bi bi-check2-circle"></i></div>
                    <div>
                        <div class="qc-tile-label">QC Selesai</div>
                        <div class="qc-tile-value" id="sum_completed">0</div>
                        <div class="qc-tile-sub" id="sum_completed_pct">0% dari total</div>
                    </div>
                </div>
                <div class="qc-tile qc-tile--amber">
                    <div class="qc-tile-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="qc-tile-label">Belum Lengkap</div>
                        <div class="qc-tile-value" id="sum_in_progress">0</div>
                        <div class="qc-tile-sub">resi masih berjalan</div>
                    </div>
                </div>
                <div class="qc-tile qc-tile--indigo">
                    <div class="qc-tile-icon"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <div class="qc-tile-label">Qty Discan / Wajib</div>
                        <div class="qc-tile-value" id="sum_qty">0 / 0</div>
                        <div class="qc-tile-sub" id="sum_qty_pct">0% qty terverifikasi</div>
                    </div>
                </div>
            </div>

            {{-- Tabel --}}
            <div class="table-responsive">
                <table class="table align-middle table-row-bordered fs-7 gy-3" id="qc_scan_history_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bolder text-uppercase gs-0">
                            <th class="text-center" width="48">No</th>
                            <th>Resi &amp; Pesanan</th>
                            <th>Petugas QC</th>
                            <th>Waktu Scan</th>
                            <th>SKU</th>
                            <th width="200">Progress Qty</th>
                            <th>Status QC</th>
                            <th class="text-end qc-no-print" width="90">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            {{-- Tanda tangan (hanya tampil saat dicetak) --}}
            <div class="qc-doc-sign">
                <div class="qc-sign-box">
                    <div>Disiapkan oleh,</div>
                    <div class="qc-sign-space"></div>
                    <div class="qc-sign-line">{{ $generatedBy ?? '( ............................ )' }}</div>
                    <div>Petugas Laporan</div>
                </div>
                <div class="qc-sign-box">
                    <div>Mengetahui,</div>
                    <div class="qc-sign-space"></div>
                    <div class="qc-sign-line">( ............................ )</div>
                    <div>Kepala Gudang</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============ Modal Detail ============ --}}
<div class="modal fade" id="modal_qc_resi_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="qc_detail_title">Detail QC Resi</h5>
                    <div class="text-muted fs-7" id="qc_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 mb-5" id="qc_detail_pills"></div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h6 class="fw-bold text-gray-700 mb-0">
                        <i class="bi bi-box-seam me-1 text-primary"></i>Daftar SKU Resi
                    </h6>
                    <div class="d-flex align-items-center position-relative">
                        <i class="bi bi-search position-absolute ms-3 text-gray-500 fs-5"></i>
                        <input type="text" class="form-control form-control-solid w-250px ps-10" id="qc_detail_items_search" placeholder="Cari SKU / nama" />
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="8%">No</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end">Scan / Wajib</th>
                                <th>Status QC</th>
                            </tr>
                        </thead>
                        <tbody id="qc_detail_items_body">
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data.</td></tr>
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
    const dataUrl      = '{{ $dataUrl }}';
    const deleteUrlTpl = '{{ route('admin.outbound.qc-scan-history.destroy', ':id') }}';
    const csrfToken    = '{{ csrf_token() }}';
    const todayStr     = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#qc_scan_history_table');
        const searchInput = document.getElementById('qc_search');
        const userSelect = document.getElementById('filter_picker_user');
        const statusSelect = document.getElementById('filter_picker_status');
        const limitSelect = document.getElementById('filter_limit');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const printBtn = document.getElementById('btn_print_report');
        const presetGroup = document.getElementById('qc_preset_group');
        const periodText = document.getElementById('qc_period_text');
        const printedAtEl = document.getElementById('qc_printed_at');
        const detailModalEl = document.getElementById('modal_qc_resi_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const detailTitleEl = document.getElementById('qc_detail_title');
        const detailSubtitleEl = document.getElementById('qc_detail_subtitle');
        const detailPillsEl = document.getElementById('qc_detail_pills');
        const detailItemsBodyEl = document.getElementById('qc_detail_items_body');
        const detailItemsSearchEl = document.getElementById('qc_detail_items_search');
        const rowDataStore = {};
        let activeDetailRow = null;
        let searchTimer = null;
        let detailSearchTimer = null;

        const ID_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const esc = v => String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        const num = v => Number(v || 0).toLocaleString('id-ID');

        const formatDateID = (ymd) => {
            if (!ymd) return '';
            const parts = String(ymd).split('-');
            if (parts.length !== 3) return ymd;
            return `${parts[2]} ${ID_MONTHS[Number(parts[1]) - 1] || parts[1]} ${parts[0]}`;
        };

        // ---------- Set "dicetak" timestamp ----------
        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        if (printedAtEl) {
            printedAtEl.textContent = `${pad(now.getDate())} ${ID_MONTHS[now.getMonth()]} ${now.getFullYear()}, ${pad(now.getHours())}:${pad(now.getMinutes())}`;
        }

        // ---------- Select2 ----------
        const select2Safe = (el, placeholder) => {
            if (el && typeof $ !== 'undefined' && $.fn.select2) {
                $(el).select2({ placeholder, allowClear: true, width: '100%' })
                    .on('select2:opening select2:closing select2:close', e => e.stopPropagation());
            }
        };
        select2Safe(userSelect, 'Semua Petugas');
        select2Safe(statusSelect, 'Semua Status');

        // ---------- Flatpickr ----------
        let fpFrom = null;
        let fpTo = null;
        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            if (dateToEl) fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
        }
        const setDate = (fp, el, value) => {
            if (fp) fp.setDate(value || null, true);
            else if (el) el.value = value || '';
        };

        // ---------- Date presets ----------
        const toYMD = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
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
            presetGroup?.querySelectorAll('.qc-preset').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-range') === range);
            });
        };
        presetGroup?.querySelectorAll('.qc-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                const range = btn.getAttribute('data-range');
                const [from, to] = computeRange(range);
                setDate(fpFrom, dateFromEl, from);
                setDate(fpTo, dateToEl, to);
                markPreset(range);
                reloadTable();
            });
        });
        // Manual date edit clears preset highlight
        [dateFromEl, dateToEl].forEach(el => el?.addEventListener('change', () => markPreset(null)));

        // ---------- Period text ----------
        const updatePeriodText = () => {
            const from = dateFromEl?.value || '';
            const to = dateToEl?.value || '';
            let period = 'Semua Tanggal';
            if (from && to) period = `${formatDateID(from)} s.d. ${formatDateID(to)}`;
            else if (from) period = `Sejak ${formatDateID(from)}`;
            else if (to) period = `Hingga ${formatDateID(to)}`;

            const userName = userSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Semua Petugas';
            const statusName = statusSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Semua Status';

            if (periodText) {
                periodText.innerHTML =
                    `Periode: <b>${esc(period)}</b><span class="sep">|</span>` +
                    `Petugas: <b>${esc(userName)}</b><span class="sep">|</span>` +
                    `Status: <b>${esc(statusName)}</b>`;
            }
        };

        // ---------- Summary ----------
        const updateSummary = (summary) => {
            const s = summary || {};
            const total = Number(s.total_resi || 0);
            const completed = Number(s.completed || 0);
            const completedPct = total > 0 ? Math.round((completed / total) * 100) : 0;
            document.getElementById('sum_total_resi').textContent = num(total);
            document.getElementById('sum_completed').textContent = num(completed);
            document.getElementById('sum_completed_pct').textContent = `${completedPct}% dari total`;
            document.getElementById('sum_in_progress').textContent = num(s.in_progress);
            document.getElementById('sum_qty').textContent = `${num(s.scanned_qty)} / ${num(s.required_qty)}`;
            document.getElementById('sum_qty_pct').textContent = `${Number(s.progress_pct || 0)}% qty terverifikasi`;
        };

        // ---------- DataTable ----------
        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            ordering: false,
            pageLength: Number(limitSelect?.value || 10),
            language: {
                processing: 'Memuat data...',
                emptyTable: 'Belum ada data QC scan.',
                zeroRecords: 'Tidak ada data yang cocok dengan filter.',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ resi',
                infoEmpty: 'Menampilkan 0 resi',
                infoFiltered: '(disaring dari _MAX_ total resi)',
                paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
            },
            ajax: {
                url: dataUrl,
                data(params) {
                    params.q = searchInput?.value || '';
                    params.user_id = userSelect?.value || '';
                    params.status = statusSelect?.value || '';
                    params.date_from = dateFromEl?.value || '';
                    params.date_to = dateToEl?.value || '';
                },
                dataSrc(json) {
                    updateSummary(json.summary);
                    updatePeriodText();
                    return json.data || [];
                },
            },
            columns: [
                { data: 'no', className: 'text-center text-muted fw-semibold' },
                {
                    data: 'no_resi',
                    render(data, type, row) {
                        rowDataStore[String(row.id)] = row;
                        return `<span class="fw-bold font-monospace text-primary">${esc(data) || '-'}</span>
                                <div class="text-muted fs-8">Pesanan: ${esc(row.id_pesanan) || '-'}</div>
                                <div class="text-muted fs-9">QC #${esc(String(row.id))}</div>`;
                    },
                },
                {
                    data: 'picker',
                    render: data => `<span class="fw-semibold text-gray-800">${esc(data) || '-'}</span>`,
                },
                {
                    data: 'scanned_at',
                    render(data, type, row) {
                        const completed = row.completed_at
                            ? `<div class="text-success fs-9"><i class="bi bi-check2 me-1"></i>Selesai: ${esc(row.completed_at)}</div>`
                            : '<div class="text-muted fs-9">Belum selesai</div>';
                        return `<span class="fw-semibold">${esc(data) || '-'}</span>${completed}`;
                    },
                },
                {
                    data: 'sku_count',
                    render(data, type, row) {
                        const count = Number(data || 0);
                        return `<button class="btn btn-sm btn-light-primary btn-detail-qc-resi px-3 py-1 qc-no-print" data-id="${esc(String(row.id))}">
                                    <i class="bi bi-box-seam me-1"></i>${count} SKU
                                </button>
                                <span class="fw-semibold d-none d-print-inline">${count} SKU</span>
                                <div class="text-muted fs-9 mt-1 text-truncate" style="max-width:240px" title="${esc(row.item || '-')}">${esc(row.item || '-')}</div>`;
                    },
                },
                {
                    data: 'qc_progress',
                    render(data, type, row) {
                        const pct = Number(data || 0);
                        const barClass = pct >= 100 ? 'bg-success' : 'bg-warning';
                        return `<div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1 h-8px bg-light" style="min-width:90px">
                                        <div class="progress-bar ${barClass}" style="width:${pct}%"></div>
                                    </div>
                                    <span class="fw-bold fs-8">${pct}%</span>
                                </div>
                                <div class="text-muted fs-9 mt-1">${num(row.scanned_qty)} / ${num(row.required_qty)} qty</div>`;
                    },
                },
                { data: 'status', render: status => qcStatusBadge(status) },
                {
                    data: 'id',
                    className: 'text-end qc-no-print',
                    render(data, type, row) {
                        if (row?.status !== 'in_progress' || Number(row?.scanned_qty || 0) > 0) {
                            return '<span class="text-muted fs-8">-</span>';
                        }
                        return `<button class="btn btn-sm btn-light-danger btn-delete px-3 py-1" data-id="${esc(String(data))}">
                                    <i class="bi bi-trash"></i>
                                </button>`;
                    },
                },
            ],
        });

        const reloadTable = () => dt.ajax.reload(null, false);

        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadTable, 400);
        });
        applyBtn?.addEventListener('click', reloadTable);
        userSelect && $(userSelect).on('change', reloadTable);
        statusSelect && $(statusSelect).on('change', reloadTable);
        limitSelect?.addEventListener('change', () => {
            dt.page.len(Number(limitSelect.value || 10)).draw();
        });
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            setSelectValue(userSelect, '');
            setSelectValue(statusSelect, '');
            if (limitSelect) { limitSelect.value = '10'; dt.page.len(10); }
            setDate(fpFrom, dateFromEl, '');
            setDate(fpTo, dateToEl, '');
            markPreset('all');
            reloadTable();
        });

        printBtn?.addEventListener('click', () => window.print());

        tableEl.on('click', '.btn-detail-qc-resi', function() {
            const row = rowDataStore[this.getAttribute('data-id')];
            if (!row || !detailModal) return;
            activeDetailRow = row;
            if (detailItemsSearchEl) detailItemsSearchEl.value = '';
            renderDetail(row);
            detailModal.show();
        });

        detailItemsSearchEl?.addEventListener('input', () => {
            clearTimeout(detailSearchTimer);
            detailSearchTimer = setTimeout(() => renderDetailItems(activeDetailRow), 200);
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            const confirmed = await swalConfirm('Hapus resi QC?', 'Resi QC yang belum memiliki scan SKU akan dihapus.', 'Hapus');
            if (!confirmed) return;
            try {
                const { ok, json } = await apiAction({
                    url: deleteUrlTpl.replace(':id', id),
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                swalResult(ok, json?.message || (ok ? 'Resi QC berhasil dihapus' : 'Gagal menghapus resi QC'));
                if (ok) reloadTable();
            } catch {
                swalResult(false, 'Gagal menghapus resi QC');
            }
        });

        function renderDetail(row) {
            if (detailTitleEl) detailTitleEl.textContent = row.no_resi || '-';
            if (detailSubtitleEl) detailSubtitleEl.textContent = `${row.id_pesanan || '-'} • ${row.picker || '-'}`;
            if (detailPillsEl) {
                detailPillsEl.innerHTML = [
                    qcStatusBadge(row.status),
                    pill('bi-calendar-event', 'text-primary', 'Scan', esc(row.scanned_at || '-')),
                    pill('bi-flag', 'text-success', 'Selesai', esc(row.completed_at || '-')),
                    pill('bi-person', 'text-info', 'Petugas', esc(row.picker || '-')),
                    pill('bi-box-seam', 'text-warning', 'Qty QC', `${num(row.scanned_qty)} / ${num(row.required_qty)}`),
                    pill('bi-graph-up', 'text-primary', 'Progress', `${row.qc_progress ?? 0}%`),
                ].join('');
            }
            renderDetailItems(row);
        }

        function renderDetailItems(row) {
            if (!detailItemsBodyEl) return;
            const keyword = (detailItemsSearchEl?.value || '').trim().toLowerCase();
            let items = Array.isArray(row?.items) ? row.items : [];
            if (keyword !== '') {
                items = items.filter(it => String(it.sku || '').toLowerCase().includes(keyword)
                    || String(it.name || '').toLowerCase().includes(keyword));
            }
            if (!items.length) {
                detailItemsBodyEl.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada SKU yang cocok.</td></tr>`;
                return;
            }
            detailItemsBodyEl.innerHTML = items.map((it, idx) => `
                <tr>
                    <td class="text-muted">${idx + 1}</td>
                    <td><span class="fw-bold font-monospace">${esc(it.sku)}</span></td>
                    <td>${esc(it.name)}</td>
                    <td class="text-end fw-semibold">${num(it.scanned_qty)} / ${num(it.required_qty)}</td>
                    <td>${qcStatusBadge(it.status)}</td>
                </tr>`).join('');
        }

        function setSelectValue(el, value) {
            if (!el) return;
            if (typeof $ !== 'undefined' && $(el).data('select2')) $(el).val(value).trigger('change.select2');
            else el.value = value;
        }

        function qcStatusBadge(status) {
            if (status === 'completed') {
                return '<span class="qc-status-badge qc-status-badge--done"><i class="bi bi-check-circle-fill"></i>Selesai</span>';
            }
            return '<span class="qc-status-badge qc-status-badge--wip"><i class="bi bi-hourglass-split"></i>Belum Lengkap</span>';
        }

        function pill(icon, colorClass, label, value) {
            return `<span class="d-inline-flex align-items-center gap-1 px-3 py-2 rounded bg-light">
                        <i class="bi ${esc(icon)} ${esc(colorClass)} fs-7"></i>
                        <span class="text-muted fs-8">${esc(label)}:</span>
                        <span class="fw-semibold fs-7">${value}</span>
                    </span>`;
        }

        async function apiAction({ url, method = 'POST', body = null }) {
            const opts = { method, headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } };
            if (body) {
                opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                opts.body = body;
            }
            const res = await fetch(url, opts);
            const text = await res.text();
            let json = null;
            try { json = JSON.parse(text); } catch {}
            return { ok: res.ok, status: res.status, json };
        }

        async function swalConfirm(title, text, confirmText) {
            if (typeof Swal === 'undefined') return window.confirm(`${title}\n${text}`);
            const res = await Swal.fire({
                title, text, icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Batal',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
            });
            return res.isConfirmed;
        }

        function swalResult(ok, msg) {
            if (typeof Swal !== 'undefined') Swal.fire(ok ? 'Berhasil' : 'Error', msg, ok ? 'success' : 'error');
            else alert(msg);
        }

        updatePeriodText();
    });
</script>
@endpush
