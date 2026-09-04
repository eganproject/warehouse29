@extends('layouts.admin')

@section('title', 'Forecast Pengadaan')
@section('page_title', 'Forecast Pengadaan')

@push('styles')
<style>
    .forecast-hero {
        background: linear-gradient(125deg, #173b57 0%, #285f75 55%, #2f7a71 100%);
        border-radius: 1rem;
        color: #fff;
        overflow: hidden;
        position: relative;
    }
    .forecast-hero::after {
        background: rgba(255, 255, 255, .06);
        border-radius: 50%;
        content: '';
        height: 290px;
        position: absolute;
        right: -80px;
        top: -125px;
        width: 290px;
    }
    .forecast-formula {
        background: rgba(255, 255, 255, .13);
        border: 1px solid rgba(255, 255, 255, .2);
        border-radius: .8rem;
        max-width: 410px;
        padding: 1rem 1.15rem;
        position: relative;
        z-index: 1;
    }
    .forecast-card,
    .forecast-metric,
    .forecast-analysis,
    .forecast-table-card {
        border: 1px solid #edf0f5;
        box-shadow: 0 3px 16px rgba(28, 45, 70, .045);
    }
    .forecast-card,
    .forecast-analysis,
    .forecast-table-card { border-radius: .85rem; }
    .forecast-label {
        color: #64748b;
        display: block;
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .025em;
        margin-bottom: .45rem;
        text-transform: uppercase;
    }
    .forecast-day-input { position: relative; }
    .forecast-day-input input { font-size: 1.05rem; font-weight: 700; padding-right: 4.2rem; }
    .forecast-day-input span {
        color: #7b8798;
        font-size: .78rem;
        font-weight: 600;
        pointer-events: none;
        position: absolute;
        right: 1.1rem;
        top: 50%;
        transform: translateY(-50%);
    }
    .forecast-presets .btn { border-radius: 2rem; font-size: .72rem; padding: .35rem .75rem; }
    .forecast-period {
        align-items: center;
        background: #f0f8f7;
        border: 1px solid #d9efeb;
        border-radius: .7rem;
        color: #3e6661;
        display: flex;
        font-size: .78rem;
        gap: .65rem;
        padding: .7rem .9rem;
    }
    .forecast-metric {
        border-radius: .85rem;
        height: 100%;
        padding: 1.25rem;
        position: relative;
    }
    .forecast-metric::before {
        background: var(--metric-color, #3b82f6);
        border-radius: 1rem;
        content: '';
        height: 4px;
        left: 1.25rem;
        position: absolute;
        right: 1.25rem;
        top: 0;
    }
    .forecast-metric-icon {
        align-items: center;
        background: var(--metric-bg, #eff6ff);
        border-radius: .65rem;
        color: var(--metric-color, #3b82f6);
        display: flex;
        height: 38px;
        justify-content: center;
        width: 38px;
    }
    .forecast-metric-value {
        color: #15243b;
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: -.035em;
        line-height: 1.15;
    }
    .forecast-metric-note { color: #8491a5; font-size: .77rem; min-height: 1.15rem; }
    .forecast-insight {
        align-items: flex-start;
        background: #f0f8f7;
        border: 1px solid #d9efeb;
        border-radius: .8rem;
        color: #315b58;
        display: flex;
        gap: .85rem;
        padding: 1rem 1.15rem;
    }
    .forecast-insight-icon {
        align-items: center;
        background: #dff1ed;
        border-radius: 50%;
        color: #257267;
        display: flex;
        flex: 0 0 auto;
        height: 34px;
        justify-content: center;
        width: 34px;
    }
    .forecast-analysis { height: 100%; }
    .forecast-analysis-title { color: #25344c; font-size: .95rem; font-weight: 700; }
    .forecast-analysis-subtitle { color: #8c98aa; font-size: .78rem; }
    .forecast-trend {
        align-items: flex-end;
        display: flex;
        gap: .45rem;
        height: 190px;
        min-width: 100%;
        overflow-x: auto;
        padding: 1rem .15rem 0;
    }
    .forecast-day { display: flex; flex: 1 0 27px; flex-direction: column; height: 100%; justify-content: flex-end; min-width: 27px; }
    .forecast-day-bars { align-items: flex-end; display: flex; gap: 3px; height: 145px; justify-content: center; }
    .forecast-day-bar { border-radius: 4px 4px 2px 2px; min-height: 3px; width: 8px; }
    .forecast-day-label { color: #98a3b4; font-size: .63rem; margin-top: .45rem; text-align: center; white-space: nowrap; }
    .forecast-legend { color: #7e8a9d; display: flex; flex-wrap: wrap; font-size: .72rem; gap: 1rem; }
    .forecast-legend span::before { background: var(--legend-color); border-radius: 50%; content: ''; display: inline-block; height: 7px; margin-right: .35rem; width: 7px; }
    .forecast-rank + .forecast-rank { margin-top: 1rem; }
    .forecast-rank-meta { align-items: flex-start; color: #778399; display: flex; font-size: .75rem; gap: .75rem; justify-content: space-between; margin-bottom: .35rem; }
    .forecast-rank-label { color: #34445d; font-size: .82rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .forecast-rank-track { background: #eff2f7; border-radius: 1rem; height: 6px; overflow: hidden; }
    .forecast-rank-fill { background: linear-gradient(90deg, #2e7d72, #55a999); border-radius: inherit; height: 100%; min-width: 3px; }
    .forecast-empty { align-items: center; color: #9aa5b5; display: flex; flex-direction: column; font-size: .82rem; gap: .6rem; justify-content: center; min-height: 145px; text-align: center; }
    .forecast-empty i { font-size: 1.6rem; opacity: .55; }
    .forecast-table-card .table thead th { color: #8290a5; font-size: .69rem; letter-spacing: .04em; white-space: nowrap; }
    .forecast-table-card .table tbody td { vertical-align: middle; }
    .forecast-sku { color: #23435f; font-weight: 700; }
    .forecast-number { color: #34445d; font-variant-numeric: tabular-nums; font-weight: 600; }
    .forecast-purchase { color: #b24545; font-size: 1rem; font-weight: 700; }
    .forecast-days-bar { background: #edf1f5; border-radius: 1rem; height: 5px; margin-top: .4rem; overflow: hidden; width: 90px; }
    .forecast-days-fill { background: #2f8073; border-radius: inherit; height: 100%; }
    @media (max-width: 767.98px) {
        .forecast-formula { max-width: 100%; }
        .forecast-metric-value { font-size: 1.45rem; }
        .forecast-trend { height: 170px; }
    }
</style>
@endpush

@section('content')
<div class="forecast-hero mb-6 p-6 p-lg-8">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-6 position-relative" style="z-index:1">
        <div style="max-width:650px">
            <div class="d-flex align-items-center gap-2 mb-3 text-white-50 fw-semibold fs-7 text-uppercase"><i class="fa-solid fa-chart-area"></i> Perencanaan Persediaan</div>
            <h2 class="text-white fw-bold mb-2">Forecast stok dan rekomendasi pengadaan</h2>
            <p class="mb-0 text-white-50">Menggunakan penjualan yang telah Scan Out. Kebutuhan bundle otomatis diterjemahkan menjadi kebutuhan komponen stok.</p>
        </div>
        <div class="forecast-formula">
            <div class="text-white-50 fs-8 fw-semibold text-uppercase mb-2">Rumus rekomendasi</div>
            <div class="fw-bold mb-1">Target stok = rata-rata harian × hari persediaan</div>
            <div class="text-white-50 fs-8">Pengadaan = target stok − stok saat ini, minimum 0. Safety stock dapat ditambahkan sebagai buffer.</div>
        </div>
    </div>
</div>

<div class="card forecast-card mb-5">
    <div class="card-body p-5">
        <div class="row g-4 align-items-end">
            <div class="col-xl-2 col-md-4 col-6">
                <label class="forecast-label">Rata-rata Penjualan</label>
                <div class="forecast-day-input"><input type="number" id="history_days" class="form-control form-control-solid" min="1" max="365" value="{{ $defaultHistoryDays }}"><span>hari terakhir</span></div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <label class="forecast-label">Target Persediaan</label>
                <div class="forecast-day-input"><input type="number" id="coverage_days" class="form-control form-control-solid" min="1" max="365" value="{{ $defaultCoverageDays }}"><span>hari ke depan</span></div>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="forecast-label">Kategori</label>
                <select id="forecast_category" class="form-select form-select-solid">
                    <option value="">Semua kategori</option>
                    <option value="0">Tanpa Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="forecast-label">Kondisi Forecast</label>
                <select id="forecast_status" class="form-select form-select-solid">
                    <option value="">Semua kondisi</option>
                    <option value="critical">Kritis — stok habis</option>
                    <option value="reorder">Perlu pengadaan — stok masih ada</option>
                    <option value="sufficient">Stok mencukupi</option>
                    <option value="no_sales">Tanpa penjualan</option>
                </select>
            </div>
            <div class="col-xl-4 col-md-8">
                <label class="forecast-label">Pencarian</label>
                <div class="position-relative"><i class="fa-solid fa-magnifying-glass position-absolute top-50 translate-middle-y ms-4 text-muted"></i><input type="text" id="forecast_search" class="form-control form-control-solid ps-10" placeholder="Cari SKU, nama, kategori, atau alamat"></div>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <label class="form-check form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" id="include_safety_stock">
                    <span class="form-check-label fw-semibold">Tambahkan safety stock ke target</span>
                </label>
                <div class="forecast-presets d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-light forecast-preset" data-history="14" data-coverage="14">14 → 14 hari</button>
                    <button type="button" class="btn btn-light forecast-preset" data-history="30" data-coverage="30">30 → 30 hari</button>
                    <button type="button" class="btn btn-light forecast-preset" data-history="60" data-coverage="30">60 → 30 hari</button>
                    <button type="button" class="btn btn-light forecast-preset" data-history="90" data-coverage="60">90 → 60 hari</button>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light" id="forecast_reset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                <button type="button" class="btn btn-primary" id="forecast_apply"><i class="fa-solid fa-wand-magic-sparkles"></i> Hitung Forecast</button>
            </div>
        </div>
        <div class="forecast-period mt-4" id="forecast_period"><i class="fa-regular fa-calendar"></i><span>Menyiapkan periode analisis...</span></div>
        <div class="alert alert-danger d-none mt-4 mb-0 py-3" id="forecast_error"></div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-6 col-lg-4 col-xxl-2"><div class="forecast-metric bg-white" style="--metric-color:#315d7a;--metric-bg:#eaf1f5"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">SKU Dianalisis</span><span class="forecast-metric-icon"><i class="fa-solid fa-barcode"></i></span></div><div class="forecast-metric-value" data-kpi="total_sku">0</div><div class="forecast-metric-note">SKU stok aktif</div></div></div>
    <div class="col-6 col-lg-4 col-xxl-2"><div class="forecast-metric bg-white" style="--metric-color:#5470c6;--metric-bg:#eef1fb"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Kebutuhan Historis</span><span class="forecast-metric-icon"><i class="fa-solid fa-cart-shopping"></i></span></div><div class="forecast-metric-value" data-kpi="sales_qty">0</div><div class="forecast-metric-note" data-note="sales_qty">0 unit per hari</div></div></div>
    <div class="col-6 col-lg-4 col-xxl-2"><div class="forecast-metric bg-white" style="--metric-color:#2f8073;--metric-bg:#e8f5f2"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Stok Saat Ini</span><span class="forecast-metric-icon"><i class="fa-solid fa-boxes-stacked"></i></span></div><div class="forecast-metric-value" data-kpi="current_stock">0</div><div class="forecast-metric-note" data-note="current_stock">Cakupan stok 0 hari</div></div></div>
    <div class="col-6 col-lg-4 col-xxl-2"><div class="forecast-metric bg-white" style="--metric-color:#815eb5;--metric-bg:#f3eef9"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Target Stok</span><span class="forecast-metric-icon"><i class="fa-solid fa-bullseye"></i></span></div><div class="forecast-metric-value" data-kpi="target_stock">0</div><div class="forecast-metric-note" data-note="target_stock">Untuk 30 hari persediaan</div></div></div>
    <div class="col-6 col-lg-4 col-xxl-2"><div class="forecast-metric bg-white" style="--metric-color:#d35151;--metric-bg:#fceded"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Saran Pengadaan</span><span class="forecast-metric-icon"><i class="fa-solid fa-truck-ramp-box"></i></span></div><div class="forecast-metric-value" data-kpi="suggested_purchase">0</div><div class="forecast-metric-note" data-note="suggested_purchase">0 SKU perlu dibeli</div></div></div>
    <div class="col-6 col-lg-4 col-xxl-2"><div class="forecast-metric bg-white" style="--metric-color:#d99424;--metric-bg:#fff6e5"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">SKU Kritis</span><span class="forecast-metric-icon"><i class="fa-solid fa-triangle-exclamation"></i></span></div><div class="forecast-metric-value" data-kpi="critical_sku">0</div><div class="forecast-metric-note">Ada demand, stok habis</div></div></div>
</div>

<div class="forecast-insight mb-5"><span class="forecast-insight-icon"><i class="fa-solid fa-lightbulb"></i></span><div><div class="fw-bold mb-1">Ringkasan rekomendasi</div><div class="fs-7" id="forecast_insight">Memuat hasil forecast...</div></div></div>

<div class="row g-5 mb-5">
    <div class="col-xl-7"><div class="card forecast-analysis"><div class="card-body p-5"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><div><div class="forecast-analysis-title">Tren Kebutuhan Stok</div><div class="forecast-analysis-subtitle">Penjualan langsung dan kebutuhan komponen dari bundle</div></div><div class="forecast-legend"><span style="--legend-color:#2f8073">Langsung</span><span style="--legend-color:#815eb5">Dari bundle</span></div></div><div class="forecast-trend" id="forecast_trend"></div></div></div></div>
    <div class="col-xl-5"><div class="card forecast-analysis"><div class="card-body p-5"><div class="forecast-analysis-title">Prioritas Pengadaan Terbesar</div><div class="forecast-analysis-subtitle mb-5">SKU dengan rekomendasi beli tertinggi</div><div id="forecast_top"></div></div></div></div>
    <div class="col-lg-6"><div class="card forecast-analysis"><div class="card-body p-5"><div class="forecast-analysis-title">Pengadaan per Kategori</div><div class="forecast-analysis-subtitle mb-5">Distribusi unit yang perlu disiapkan</div><div id="forecast_categories"></div></div></div></div>
    <div class="col-lg-6"><div class="card forecast-analysis"><div class="card-body p-5"><div class="forecast-analysis-title">Kondisi Persediaan</div><div class="forecast-analysis-subtitle mb-5">Jumlah SKU berdasarkan hasil forecast</div><div id="forecast_status_chart"></div></div></div></div>
</div>

<div class="card forecast-table-card">
    <div class="card-header border-0 pt-5"><div><h3 class="card-title fw-bold mb-1">Detail Rekomendasi per SKU</h3><div class="text-muted fs-8" id="forecast_updated">Menyiapkan data...</div></div><div class="card-toolbar"><label class="d-flex align-items-center gap-2 text-muted fs-8">Tampilkan <select id="forecast_limit" class="form-select form-select-sm form-select-solid w-80px"><option>10</option><option>25</option><option>50</option><option>100</option></select></label></div></div>
    <div class="card-body pt-2"><div class="table-responsive"><table class="table align-middle table-row-dashed gy-4" id="forecast_table"><thead><tr class="text-uppercase"><th>Kondisi</th><th>SKU / Produk</th><th>Kategori / Lokasi</th><th>Kebutuhan Historis</th><th class="text-end">Rata-rata / Hari</th><th class="text-end">Stok Saat Ini</th><th>Cakupan Stok</th><th class="text-end">Target Stok</th><th class="text-end">Saran Pengadaan</th><th>Penjualan Terakhir</th></tr></thead><tbody></tbody></table></div></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json($dataUrl);
    const defaultHistory = Number(@json($defaultHistoryDays));
    const defaultCoverage = Number(@json($defaultCoverageDays));
    const elements = {
        history: document.getElementById('history_days'),
        coverage: document.getElementById('coverage_days'),
        category: document.getElementById('forecast_category'),
        status: document.getElementById('forecast_status'),
        search: document.getElementById('forecast_search'),
        safety: document.getElementById('include_safety_stock'),
        period: document.getElementById('forecast_period'),
        error: document.getElementById('forecast_error'),
        insight: document.getElementById('forecast_insight'),
        updated: document.getElementById('forecast_updated'),
        limit: document.getElementById('forecast_limit'),
    };
    const numberText = (value, decimals = 0) => {
        const parsedDecimals = Number(decimals);
        const precision = Number.isInteger(parsedDecimals)
            ? Math.min(20, Math.max(0, parsedDecimals))
            : 0;

        return Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: precision });
    };
    const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
    const emptyHtml = (text = 'Belum ada data untuk ditampilkan') => `<div class="forecast-empty"><i class="fa-regular fa-folder-open"></i><span>${esc(text)}</span></div>`;
    const dateText = (value, compact = false) => {
        if (!value) return '-';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return esc(value);
        return date.toLocaleDateString('id-ID', compact ? { day: '2-digit', month: 'short' } : { day: '2-digit', month: 'short', year: 'numeric' });
    };
    const params = () => ({
        history_days: elements.history.value,
        coverage_days: elements.coverage.value,
        category_id: elements.category.value,
        status: elements.status.value,
        q: elements.search.value.trim(),
        include_safety_stock: elements.safety.checked ? 1 : 0,
    });
    const statusBadge = (status) => {
        const map = {
            critical: ['Kritis', 'badge-light-danger', 'Stok habis sementara masih ada kebutuhan'],
            reorder: ['Perlu Pengadaan', 'badge-light-warning', 'Stok belum cukup untuk target hari'],
            sufficient: ['Mencukupi', 'badge-light-success', 'Stok memenuhi target persediaan'],
            no_sales: ['Tanpa Penjualan', 'badge-light-secondary', 'Tidak ada penjualan pada periode analisis'],
        };
        const item = map[status] || [status || '-', 'badge-light', ''];
        return `<span class="badge ${item[1]}" title="${esc(item[2])}">${esc(item[0])}</span>`;
    };

    function setKpi(key, value) {
        const element = document.querySelector(`[data-kpi="${key}"]`);
        if (element) element.textContent = numberText(value);
    }
    function setNote(key, text) {
        const element = document.querySelector(`[data-note="${key}"]`);
        if (element) element.textContent = text;
    }
    function renderRanks(container, rows, unit, formatter = null) {
        if (!Array.isArray(rows) || !rows.length || !rows.some((row) => Number(row.value) > 0)) {
            container.innerHTML = emptyHtml();
            return;
        }
        const max = Math.max(...rows.map((row) => Number(row.value || 0)), 1);
        container.innerHTML = rows.map((row) => {
            const description = row.description ? `<div class="text-muted fs-8 text-truncate" title="${esc(row.description)}">${esc(row.description)}</div>` : '';
            const extra = formatter ? formatter(row) : '';
            return `<div class="forecast-rank"><div class="forecast-rank-meta"><div style="min-width:0"><div class="forecast-rank-label" title="${esc(row.label)}">${esc(row.label)}</div>${description}</div><strong class="text-nowrap">${numberText(row.value)} ${esc(unit)}${extra}</strong></div><div class="forecast-rank-track"><div class="forecast-rank-fill" style="width:${Math.max(3, (Number(row.value || 0) / max) * 100)}%"></div></div></div>`;
        }).join('');
    }
    function aggregateTrend(rows) {
        if (rows.length <= 90) return rows.map((row) => ({ ...row, label: dateText(row.date, true), title: dateText(row.date) }));
        const result = [];
        for (let index = 0; index < rows.length; index += 7) {
            const chunk = rows.slice(index, index + 7);
            result.push({
                direct_qty: chunk.reduce((sum, row) => sum + Number(row.direct_qty || 0), 0),
                bundle_qty: chunk.reduce((sum, row) => sum + Number(row.bundle_qty || 0), 0),
                label: dateText(chunk[0].date, true),
                title: `${dateText(chunk[0].date)} – ${dateText(chunk[chunk.length - 1].date)}`,
            });
        }
        return result;
    }
    function renderTrend(rows) {
        const container = document.getElementById('forecast_trend');
        if (!Array.isArray(rows) || !rows.length) {
            container.innerHTML = emptyHtml();
            return;
        }
        const points = aggregateTrend(rows);
        const max = Math.max(...points.flatMap((row) => [Number(row.direct_qty || 0), Number(row.bundle_qty || 0)]), 1);
        container.innerHTML = points.map((row) => {
            const direct = Number(row.direct_qty || 0);
            const bundle = Number(row.bundle_qty || 0);
            const title = `${row.title} · ${numberText(direct)} langsung · ${numberText(bundle)} dari bundle`;
            return `<div class="forecast-day" title="${esc(title)}"><div class="forecast-day-bars"><div class="forecast-day-bar" style="height:${Math.max(3, (direct / max) * 100)}%;background:#2f8073"></div><div class="forecast-day-bar" style="height:${Math.max(3, (bundle / max) * 100)}%;background:#815eb5"></div></div><div class="forecast-day-label">${esc(row.label)}</div></div>`;
        }).join('');
    }
    function updateDashboard(json) {
        const summary = json.summary || {};
        const analytics = json.analytics || {};
        const parameters = json.parameters || {};
        Object.entries(summary).forEach(([key, value]) => setKpi(key, value));
        setNote('sales_qty', `${numberText(summary.average_daily_sales, 2)} unit per hari`);
        setNote('current_stock', summary.overall_days_cover === null ? 'Belum ada kebutuhan historis' : `Cakupan agregat ${numberText(summary.overall_days_cover, 1)} hari`);
        setNote('target_stock', `Untuk ${numberText(parameters.coverage_days)} hari${parameters.include_safety_stock ? ' + safety stock' : ''}`);
        setNote('suggested_purchase', `${numberText(summary.purchase_sku)} SKU perlu dibeli`);
        elements.period.querySelector('span').textContent = `Penjualan Scan Out ${dateText(parameters.date_from)} – ${dateText(parameters.date_to)} (${numberText(parameters.history_days)} hari) · target stok ${numberText(parameters.coverage_days)} hari${parameters.include_safety_stock ? ' termasuk safety stock' : ''}.`;
        elements.insight.innerHTML = `<strong>${numberText(summary.purchase_sku)} SKU</strong> membutuhkan pengadaan sebanyak <strong>${numberText(summary.suggested_purchase)} unit</strong>. ${numberText(summary.critical_sku)} SKU sudah kehabisan stok, sementara ${numberText(summary.sufficient_sku)} SKU masih memenuhi target persediaan.`;
        elements.updated.textContent = `Dihitung ${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} · formula menggunakan ${numberText(parameters.history_days)} hari penjualan`;
        elements.history.value = parameters.history_days;
        elements.coverage.value = parameters.coverage_days;
        renderTrend(analytics.daily || []);
        renderRanks(document.getElementById('forecast_top'), analytics.top_procurement || [], 'unit', (row) => row.secondary === null ? '' : ` · cover ${numberText(row.secondary, 1)} hari`);
        renderRanks(document.getElementById('forecast_categories'), analytics.categories || [], 'unit', (row) => ` · ${numberText(row.sku_count)} SKU`);
        renderRanks(document.getElementById('forecast_status_chart'), analytics.status || [], 'SKU');
    }

    const table = $('#forecast_table').DataTable({
        processing: true,
        serverSide: true,
        ordering: false,
        pageLength: 10,
        dom: 'rtip',
        ajax: {
            url: dataUrl,
            data: (request) => Object.assign(request, params()),
            dataSrc: (json) => {
                elements.error.classList.add('d-none');
                updateDashboard(json || {});
                return json.data || [];
            },
            error: (xhr) => {
                elements.error.textContent = xhr.responseJSON?.message || 'Forecast gagal dihitung. Periksa parameter lalu coba lagi.';
                elements.error.classList.remove('d-none');
            },
        },
        columns: [
            { data: 'forecast_status', render: statusBadge },
            { data: null, render: (row) => `<div class="forecast-sku">${esc(row.sku)}</div><div class="text-muted fs-8 mt-1 text-truncate" style="max-width:220px" title="${esc(row.name)}">${esc(row.name)}</div>` },
            { data: null, render: (row) => `<div class="fw-semibold text-gray-700">${esc(row.category)}</div><div class="text-muted fs-8 mt-1"><i class="fa-solid fa-location-dot me-1"></i>${esc(row.address)}</div>` },
            { data: null, render: (row) => `<div class="forecast-number">${numberText(row.sales_qty)} unit</div><div class="text-muted fs-8 mt-1">Langsung ${numberText(row.direct_sales_qty)} · bundle ${numberText(row.bundle_demand_qty)}</div>` },
            { data: 'average_daily_sales', className: 'text-end forecast-number', render: (value) => numberText(value, 2) },
            { data: 'current_stock', className: 'text-end forecast-number', render: (value) => numberText(value) },
            { data: null, render: (row) => {
                if (row.days_cover === null) return '<span class="text-muted">Tidak terukur</span>';
                const width = Math.min(100, (Number(row.days_cover) / Math.max(1, Number(elements.coverage.value))) * 100);
                const color = row.forecast_status === 'critical' ? '#d35151' : row.forecast_status === 'reorder' ? '#d99424' : '#2f8073';
                return `<div class="forecast-number">${numberText(row.days_cover, 1)} hari</div><div class="forecast-days-bar"><div class="forecast-days-fill" style="width:${width}%;background:${color}"></div></div>`;
            } },
            { data: null, className: 'text-end', render: (row) => `<div class="forecast-number">${numberText(row.target_stock)}</div>${elements.safety.checked ? `<div class="text-muted fs-8">termasuk safety ${numberText(row.safety_stock)}</div>` : ''}` },
            { data: 'suggested_purchase', className: 'text-end', render: (value) => Number(value) > 0 ? `<span class="forecast-purchase">${numberText(value)}</span>` : '<span class="text-success fw-bold">0</span>' },
            { data: 'last_sale_at', render: (value) => value ? `<div class="text-nowrap">${dateText(value)}</div><div class="text-muted fs-8">${esc(value.slice(11, 16))}</div>` : '<span class="text-muted">Belum ada</span>' },
        ],
        language: {
            processing: 'Menghitung forecast...',
            emptyTable: 'Tidak ada SKU pada filter ini',
            zeroRecords: 'Data tidak ditemukan',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ SKU',
            infoEmpty: 'Tidak ada SKU',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
        },
    });

    const reload = () => table.ajax.reload(null, true);
    document.getElementById('forecast_apply').addEventListener('click', reload);
    elements.search.addEventListener('keydown', (event) => { if (event.key === 'Enter') reload(); });
    elements.limit.addEventListener('change', () => table.page.len(Number(elements.limit.value)).draw());
    document.querySelectorAll('.forecast-preset').forEach((button) => button.addEventListener('click', () => {
        elements.history.value = button.dataset.history;
        elements.coverage.value = button.dataset.coverage;
        reload();
    }));
    document.getElementById('forecast_reset').addEventListener('click', () => {
        elements.history.value = defaultHistory;
        elements.coverage.value = defaultCoverage;
        elements.category.value = '';
        elements.status.value = '';
        elements.search.value = '';
        elements.safety.checked = false;
        elements.limit.value = '10';
        table.page.len(10);
        reload();
    });
});
</script>
@endpush
