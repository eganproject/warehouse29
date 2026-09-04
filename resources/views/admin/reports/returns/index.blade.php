@extends('layouts.admin')

@section('title', 'Laporan Retur')
@section('page_title', 'Laporan Retur')

@push('styles')
<style>
    .return-report-hero {
        background: linear-gradient(125deg, #173b57 0%, #285f75 55%, #2f7a71 100%);
        border-radius: 1rem;
        color: #fff;
        overflow: hidden;
        position: relative;
    }
    .return-report-hero::after {
        background: rgba(255, 255, 255, .06);
        border-radius: 50%;
        content: '';
        height: 260px;
        position: absolute;
        right: -80px;
        top: -110px;
        width: 260px;
    }
    .return-report-tabs {
        background: rgba(255, 255, 255, .13);
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: .8rem;
        gap: .35rem;
        padding: .35rem;
        position: relative;
        z-index: 1;
    }
    .return-report-tabs .nav-link {
        border-radius: .6rem;
        color: rgba(255, 255, 255, .78);
        font-weight: 600;
        padding: .8rem 1.15rem;
    }
    .return-report-tabs .nav-link.active {
        background: #fff;
        color: #173b57;
        box-shadow: 0 6px 18px rgba(8, 34, 52, .2);
    }
    .report-filter-card,
    .return-metric-card,
    .return-analysis-card,
    .return-table-card {
        border: 1px solid #edf0f5;
        box-shadow: 0 3px 16px rgba(28, 45, 70, .045);
    }
    .report-filter-label {
        color: #64748b;
        display: block;
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .025em;
        margin-bottom: .45rem;
        text-transform: uppercase;
    }
    .return-metric-card {
        border-radius: .85rem;
        height: 100%;
        padding: 1.25rem;
        position: relative;
    }
    .return-metric-card::before {
        background: var(--metric-color, #3b82f6);
        border-radius: 1rem;
        content: '';
        height: 4px;
        left: 1.25rem;
        position: absolute;
        right: 1.25rem;
        top: 0;
    }
    .return-metric-icon {
        align-items: center;
        background: var(--metric-bg, #eff6ff);
        border-radius: .65rem;
        color: var(--metric-color, #3b82f6);
        display: flex;
        height: 38px;
        justify-content: center;
        width: 38px;
    }
    .return-metric-value {
        color: #15243b;
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: -.035em;
        line-height: 1.15;
    }
    .return-metric-note {
        color: #8491a5;
        font-size: .77rem;
        min-height: 1.15rem;
    }
    .return-insight {
        align-items: flex-start;
        background: #f0f8f7;
        border: 1px solid #d9efeb;
        border-radius: .8rem;
        color: #315b58;
        display: flex;
        gap: .85rem;
        padding: 1rem 1.15rem;
    }
    .return-insight-icon {
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
    .return-analysis-card { border-radius: .85rem; height: 100%; }
    .return-analysis-title { color: #25344c; font-size: .95rem; font-weight: 700; }
    .return-analysis-subtitle { color: #8c98aa; font-size: .78rem; }
    .return-trend {
        align-items: flex-end;
        display: flex;
        gap: .55rem;
        height: 190px;
        min-width: 100%;
        overflow-x: auto;
        padding: 1rem .15rem 0;
    }
    .return-day { display: flex; flex: 1 0 30px; flex-direction: column; height: 100%; justify-content: flex-end; min-width: 30px; }
    .return-day-bars { align-items: flex-end; display: flex; gap: 3px; height: 145px; justify-content: center; }
    .return-day-bar { border-radius: 4px 4px 2px 2px; min-height: 3px; transition: height .25s ease; width: 8px; }
    .return-day-label { color: #98a3b4; font-size: .65rem; margin-top: .45rem; text-align: center; white-space: nowrap; }
    .return-legend { align-items: center; color: #7e8a9d; display: flex; flex-wrap: wrap; font-size: .72rem; gap: 1rem; }
    .return-legend span::before { background: var(--legend-color); border-radius: 50%; content: ''; display: inline-block; height: 7px; margin-right: .35rem; width: 7px; }
    .rank-row + .rank-row { margin-top: 1rem; }
    .rank-meta { color: #778399; display: flex; font-size: .75rem; justify-content: space-between; margin-bottom: .35rem; }
    .rank-label { color: #34445d; font-size: .82rem; font-weight: 600; max-width: 72%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rank-track { background: #eff2f7; border-radius: 1rem; height: 6px; overflow: hidden; }
    .rank-fill { background: linear-gradient(90deg, #2e7d72, #55a999); border-radius: inherit; height: 100%; min-width: 3px; }
    .report-empty { align-items: center; color: #9aa5b5; display: flex; flex-direction: column; font-size: .82rem; gap: .6rem; justify-content: center; min-height: 150px; text-align: center; }
    .report-empty i { font-size: 1.65rem; opacity: .55; }
    .return-table-card { border-radius: .85rem; }
    .return-table-card .table thead th { color: #8290a5; font-size: .7rem; letter-spacing: .04em; white-space: nowrap; }
    .return-table-card .table tbody td { vertical-align: middle; }
    .return-code { color: #23435f; font-weight: 700; }
    .return-item-list { min-width: 210px; }
    .return-item-line { color: #42516a; font-size: .78rem; line-height: 1.45; }
    .return-item-line + .return-item-line { border-top: 1px dashed #edf0f4; margin-top: .35rem; padding-top: .35rem; }
    .return-qty-grid { display: grid; gap: .3rem .8rem; grid-template-columns: repeat(2, auto); min-width: 155px; }
    .return-qty-grid span { color: #7d899b; font-size: .7rem; }
    .return-qty-grid strong { color: #34445d; float: right; margin-left: .5rem; }
    .quick-range .btn { border-radius: 2rem; font-size: .72rem; padding: .35rem .7rem; }
    .report-loading { animation: reportPulse 1.3s infinite; background: #edf1f5; border-radius: .25rem; color: transparent !important; display: inline-block; min-width: 55px; }
    @keyframes reportPulse { 50% { opacity: .5; } }
    @media (max-width: 767.98px) {
        .return-report-hero .hero-copy { max-width: 100% !important; }
        .return-report-tabs { width: 100%; }
        .return-report-tabs .nav-item { flex: 1; }
        .return-report-tabs .nav-link { text-align: center; width: 100%; }
        .return-metric-value { font-size: 1.45rem; }
        .return-trend { height: 170px; }
    }
</style>
@endpush

@section('content')
<div class="return-report-hero mb-6 p-6 p-lg-8">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-6 position-relative" style="z-index:1">
        <div class="hero-copy" style="max-width:650px">
            <div class="d-flex align-items-center gap-2 mb-3 text-white-50 fw-semibold fs-7 text-uppercase">
                <i class="fa-solid fa-chart-line"></i> Pusat Analisis Retur
            </div>
            <h2 class="text-white fw-bold mb-2">Pantau arus retur dari penerimaan sampai barang keluar</h2>
            <p class="mb-0 text-white-50">Temukan selisih penerimaan, kualitas barang, penyebab retur, sumber stok, dan transaksi yang masih menunggu tindak lanjut.</p>
        </div>
        <ul class="nav nav-pills return-report-tabs flex-nowrap" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="inbound-tab" data-bs-toggle="pill" data-bs-target="#inbound-panel" type="button" role="tab" data-report-tab="inbound">
                    <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Retur Masuk
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="outbound-tab" data-bs-toggle="pill" data-bs-target="#outbound-panel" type="button" role="tab" data-report-tab="outbound">
                    <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Retur Keluar
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content">
    <div class="tab-pane fade show active" id="inbound-panel" role="tabpanel" data-report="inbound">
        <div class="card report-filter-card mb-5">
            <div class="card-body p-5">
                <div class="row g-4 align-items-end">
                    <div class="col-xl-3 col-md-6">
                        <label class="report-filter-label">Pencarian</label>
                        <div class="position-relative">
                            <i class="fa-solid fa-magnifying-glass position-absolute top-50 translate-middle-y ms-4 text-muted"></i>
                            <input type="text" class="form-control form-control-solid ps-10 report-search" placeholder="Kode, resi, order, SKU, petugas">
                        </div>
                    </div>
                    <div class="col-xl-2 col-6">
                        <label class="report-filter-label">Mulai</label>
                        <input type="date" class="form-control form-control-solid report-date-from" value="{{ $defaultDateFrom }}">
                    </div>
                    <div class="col-xl-2 col-6">
                        <label class="report-filter-label">Sampai</label>
                        <input type="date" class="form-control form-control-solid report-date-to" value="{{ $defaultDateTo }}">
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="report-filter-label">Tahap Proses</label>
                        <select class="form-select form-select-solid report-status">
                            <option value="">Semua tahap</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Gudang Retur</option>
                            <option value="finalized">Selesai / masuk stok</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-8">
                        <label class="report-filter-label">Alasan Retur</label>
                        <select class="form-select form-select-solid report-extra-filter">
                            <option value="">Semua alasan</option>
                            @foreach($returnReasons as $reason)
                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                    <div class="quick-range d-flex flex-wrap align-items-center gap-2">
                        <span class="text-muted fs-8 fw-semibold me-1">Rentang cepat:</span>
                        <button type="button" class="btn btn-light report-range" data-days="7">7 hari</button>
                        <button type="button" class="btn btn-light report-range" data-days="30">30 hari</button>
                        <button type="button" class="btn btn-light report-range" data-days="90">90 hari</button>
                        <button type="button" class="btn btn-light report-range" data-days="month">Bulan ini</button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light report-reset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                        <button type="button" class="btn btn-primary report-apply"><i class="fa-solid fa-filter"></i> Terapkan</button>
                    </div>
                </div>
                <div class="alert alert-danger d-none report-error mt-4 mb-0 py-3"></div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#315d7a;--metric-bg:#eaf1f5"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Transaksi</span><span class="return-metric-icon"><i class="fa-solid fa-receipt"></i></span></div><div class="return-metric-value" data-kpi="transactions">0</div><div class="return-metric-note" data-note="transactions">0 SKU unik</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#5470c6;--metric-bg:#eef1fb"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Qty pada Resi</span><span class="return-metric-icon"><i class="fa-solid fa-file-invoice"></i></span></div><div class="return-metric-value" data-kpi="expected_qty">0</div><div class="return-metric-note">Ekspektasi barang kembali</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#2f8073;--metric-bg:#e8f5f2"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Diterima</span><span class="return-metric-icon"><i class="fa-solid fa-box-open"></i></span></div><div class="return-metric-value" data-kpi="received_qty">0</div><div class="return-metric-note" data-note="received_qty">Tingkat terima 0%</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#d99424;--metric-bg:#fff6e5"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Selisih</span><span class="return-metric-icon"><i class="fa-solid fa-scale-unbalanced"></i></span></div><div class="return-metric-value" data-kpi="difference_qty">0</div><div class="return-metric-note">Qty resi tidak diterima</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#3b9a62;--metric-bg:#ebf7ef"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Barang Bagus</span><span class="return-metric-icon"><i class="fa-solid fa-circle-check"></i></span></div><div class="return-metric-value" data-kpi="good_qty">0</div><div class="return-metric-note" data-note="good_qty">0% dari diterima</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#d35151;--metric-bg:#fceded"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Barang Rusak</span><span class="return-metric-icon"><i class="fa-solid fa-triangle-exclamation"></i></span></div><div class="return-metric-value" data-kpi="damaged_qty">0</div><div class="return-metric-note" data-note="damaged_qty">0% dari diterima</div></div></div>
        </div>

        <div class="return-insight mb-5"><span class="return-insight-icon"><i class="fa-solid fa-lightbulb"></i></span><div><div class="fw-bold mb-1">Ringkasan tindak lanjut</div><div class="fs-7 report-insight">Memuat analisis retur masuk...</div></div></div>

        <div class="row g-5 mb-5">
            <div class="col-xl-7"><div class="card return-analysis-card"><div class="card-body p-5"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><div><div class="return-analysis-title">Tren Qty Retur Masuk</div><div class="return-analysis-subtitle">Diterima dibanding barang rusak per hari</div></div><div class="return-legend"><span style="--legend-color:#2f8073">Diterima</span><span style="--legend-color:#d35151">Rusak</span></div></div><div class="return-trend report-trend"></div></div></div></div>
            <div class="col-xl-5"><div class="card return-analysis-card"><div class="card-body p-5"><div class="return-analysis-title">SKU Retur Terbanyak</div><div class="return-analysis-subtitle mb-5">Berdasarkan qty yang diterima</div><div class="report-top-skus"></div></div></div></div>
            <div class="col-lg-6"><div class="card return-analysis-card"><div class="card-body p-5"><div class="return-analysis-title">Penyebab Retur Dominan</div><div class="return-analysis-subtitle mb-5">Qty diterima menurut alasan retur</div><div class="report-breakdown"></div></div></div></div>
            <div class="col-lg-6"><div class="card return-analysis-card"><div class="card-body p-5"><div class="return-analysis-title">Kontributor Transaksi</div><div class="return-analysis-subtitle mb-5">Jumlah transaksi per petugas input</div><div class="report-submitters"></div></div></div></div>
        </div>

        <div class="card return-table-card"><div class="card-header border-0 pt-5"><div><h3 class="card-title fw-bold mb-1">Detail Retur Masuk</h3><div class="text-muted fs-8 report-updated">Menyiapkan data...</div></div><div class="card-toolbar"><label class="d-flex align-items-center gap-2 text-muted fs-8">Tampilkan <select class="form-select form-select-sm form-select-solid report-limit w-80px"><option>10</option><option>25</option><option>50</option><option>100</option></select></label></div></div><div class="card-body pt-2"><div class="table-responsive"><table class="table align-middle table-row-dashed gy-4 report-table"><thead><tr class="text-uppercase"><th>Tanggal</th><th>Referensi</th><th>Resi / Order</th><th>Item</th><th>Qty & Kualitas</th><th>Alasan</th><th>Status</th><th>Petugas</th><th class="text-end">Detail</th></tr></thead><tbody></tbody></table></div></div></div>
    </div>

    <div class="tab-pane fade" id="outbound-panel" role="tabpanel" data-report="outbound">
        <div class="card report-filter-card mb-5"><div class="card-body p-5">
            <div class="row g-4 align-items-end">
                <div class="col-xl-4 col-md-6"><label class="report-filter-label">Pencarian</label><div class="position-relative"><i class="fa-solid fa-magnifying-glass position-absolute top-50 translate-middle-y ms-4 text-muted"></i><input type="text" class="form-control form-control-solid ps-10 report-search" placeholder="Kode, referensi, SKU, petugas"></div></div>
                <div class="col-xl-2 col-6"><label class="report-filter-label">Mulai</label><input type="date" class="form-control form-control-solid report-date-from" value="{{ $defaultDateFrom }}"></div>
                <div class="col-xl-2 col-6"><label class="report-filter-label">Sampai</label><input type="date" class="form-control form-control-solid report-date-to" value="{{ $defaultDateTo }}"></div>
                <div class="col-xl-2 col-md-6"><label class="report-filter-label">Status Approval</label><select class="form-select form-select-solid report-status"><option value="">Semua status</option><option value="pending">Pending</option><option value="approved">Approved / stok keluar</option></select></div>
                <div class="col-xl-2 col-md-6"><label class="report-filter-label">Sumber Stok</label><select class="form-select form-select-solid report-extra-filter"><option value="">Semua sumber</option><option value="regular">Stok reguler</option><option value="damaged">Stok rusak</option></select></div>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4"><div class="quick-range d-flex flex-wrap align-items-center gap-2"><span class="text-muted fs-8 fw-semibold me-1">Rentang cepat:</span><button type="button" class="btn btn-light report-range" data-days="7">7 hari</button><button type="button" class="btn btn-light report-range" data-days="30">30 hari</button><button type="button" class="btn btn-light report-range" data-days="90">90 hari</button><button type="button" class="btn btn-light report-range" data-days="month">Bulan ini</button></div><div class="d-flex gap-2"><button type="button" class="btn btn-light report-reset"><i class="fa-solid fa-rotate-left"></i> Reset</button><button type="button" class="btn btn-primary report-apply"><i class="fa-solid fa-filter"></i> Terapkan</button></div></div>
            <div class="alert alert-danger d-none report-error mt-4 mb-0 py-3"></div>
        </div></div>

        <div class="row g-4 mb-5">
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#315d7a;--metric-bg:#eaf1f5"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Transaksi</span><span class="return-metric-icon"><i class="fa-solid fa-receipt"></i></span></div><div class="return-metric-value" data-kpi="transactions">0</div><div class="return-metric-note" data-note="transactions">0 SKU unik</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#5470c6;--metric-bg:#eef1fb"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Total Qty</span><span class="return-metric-icon"><i class="fa-solid fa-boxes-stacked"></i></span></div><div class="return-metric-value" data-kpi="total_qty">0</div><div class="return-metric-note">Total rencana barang keluar</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#2f8073;--metric-bg:#e8f5f2"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Stok Reguler</span><span class="return-metric-icon"><i class="fa-solid fa-box"></i></span></div><div class="return-metric-value" data-kpi="regular_qty">0</div><div class="return-metric-note">Qty dari stok reguler</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#d35151;--metric-bg:#fceded"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Stok Rusak</span><span class="return-metric-icon"><i class="fa-solid fa-box-archive"></i></span></div><div class="return-metric-value" data-kpi="damaged_qty">0</div><div class="return-metric-note" data-note="damaged_qty">0% dari total qty</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#3b9a62;--metric-bg:#ebf7ef"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Sudah Keluar</span><span class="return-metric-icon"><i class="fa-solid fa-circle-check"></i></span></div><div class="return-metric-value" data-kpi="issued_qty">0</div><div class="return-metric-note" data-note="issued_qty">0 transaksi approved</div></div></div>
            <div class="col-6 col-lg-4 col-xxl-2"><div class="return-metric-card bg-white" style="--metric-color:#d99424;--metric-bg:#fff6e5"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-semibold fs-7">Menunggu</span><span class="return-metric-icon"><i class="fa-solid fa-clock"></i></span></div><div class="return-metric-value" data-kpi="waiting_approval_qty">0</div><div class="return-metric-note" data-note="waiting_approval_qty">0 transaksi pending</div></div></div>
        </div>

        <div class="return-insight mb-5"><span class="return-insight-icon"><i class="fa-solid fa-lightbulb"></i></span><div><div class="fw-bold mb-1">Ringkasan tindak lanjut</div><div class="fs-7 report-insight">Memuat analisis retur keluar...</div></div></div>

        <div class="row g-5 mb-5">
            <div class="col-xl-7"><div class="card return-analysis-card"><div class="card-body p-5"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><div><div class="return-analysis-title">Tren Qty Retur Keluar</div><div class="return-analysis-subtitle">Stok reguler dibanding stok rusak per hari</div></div><div class="return-legend"><span style="--legend-color:#2f8073">Reguler</span><span style="--legend-color:#d35151">Rusak</span></div></div><div class="return-trend report-trend"></div></div></div></div>
            <div class="col-xl-5"><div class="card return-analysis-card"><div class="card-body p-5"><div class="return-analysis-title">SKU Retur Keluar Terbanyak</div><div class="return-analysis-subtitle mb-5">Berdasarkan total qty barang keluar</div><div class="report-top-skus"></div></div></div></div>
            <div class="col-lg-6"><div class="card return-analysis-card"><div class="card-body p-5"><div class="return-analysis-title">Komposisi Sumber Stok</div><div class="return-analysis-subtitle mb-5">Perbandingan qty reguler dan rusak</div><div class="report-breakdown"></div></div></div></div>
            <div class="col-lg-6"><div class="card return-analysis-card"><div class="card-body p-5"><div class="return-analysis-title">Kontributor Transaksi</div><div class="return-analysis-subtitle mb-5">Jumlah transaksi per petugas input</div><div class="report-submitters"></div></div></div></div>
        </div>

        <div class="card return-table-card"><div class="card-header border-0 pt-5"><div><h3 class="card-title fw-bold mb-1">Detail Retur Keluar</h3><div class="text-muted fs-8 report-updated">Menyiapkan data...</div></div><div class="card-toolbar"><label class="d-flex align-items-center gap-2 text-muted fs-8">Tampilkan <select class="form-select form-select-sm form-select-solid report-limit w-80px"><option>10</option><option>25</option><option>50</option><option>100</option></select></label></div></div><div class="card-body pt-2"><div class="table-responsive"><table class="table align-middle table-row-dashed gy-4 report-table"><thead><tr class="text-uppercase"><th>Tanggal</th><th>Referensi</th><th>Item</th><th>Qty & Sumber</th><th>Status</th><th>Petugas</th><th>Catatan</th><th class="text-end">Detail</th></tr></thead><tbody></tbody></table></div></div></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const defaultFrom = @json($defaultDateFrom);
    const defaultTo = @json($defaultDateTo);
    const numberText = (value) => Number(value || 0).toLocaleString('id-ID');
    const percentText = (value) => `${Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
    const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
    const emptyHtml = (text = 'Belum ada data pada periode ini') => `<div class="report-empty"><i class="fa-regular fa-folder-open"></i><span>${esc(text)}</span></div>`;
    const configs = {
        inbound: {
            url: @json($inboundDataUrl),
            primaryDaily: 'received_qty',
            secondaryDaily: 'damaged_qty',
            primaryColor: '#2f8073',
            secondaryColor: '#d35151',
        },
        outbound: {
            url: @json($outboundDataUrl),
            primaryDaily: 'regular_qty',
            secondaryDaily: 'damaged_qty',
            primaryColor: '#2f8073',
            secondaryColor: '#d35151',
        }
    };

    const statusBadge = (status) => {
        const statusMap = {
            pending: ['Pending', 'badge-light-warning'],
            approved: ['Approved', 'badge-light-primary'],
            finalized: ['Selesai', 'badge-light-success'],
        };
        const item = statusMap[status] || [status || '-', 'badge-light'];
        return `<span class="badge ${item[1]}">${esc(item[0])}</span>`;
    };

    const formatDate = (value, includeYear = true) => {
        if (!value) return '-';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return esc(value);
        return date.toLocaleDateString('id-ID', includeYear
            ? { day: '2-digit', month: 'short', year: 'numeric' }
            : { day: '2-digit', month: 'short' });
    };

    const itemList = (items, type) => {
        if (!Array.isArray(items) || !items.length) return '<span class="text-muted">-</span>';
        const shown = items.slice(0, 3).map((item) => {
            const qty = type === 'inbound' ? item.qty_received : item.qty;
            return `<div class="return-item-line"><span class="fw-bold">${esc(item.sku)}</span> <span class="text-muted">· ${numberText(qty)} qty</span><div class="text-muted text-truncate" style="max-width:240px">${esc(item.name)}</div></div>`;
        }).join('');
        const more = items.length > 3 ? `<div class="text-primary fs-8 mt-2">+${numberText(items.length - 3)} item lainnya</div>` : '';
        return `<div class="return-item-list">${shown}${more}</div>`;
    };

    const qtyGrid = (row, type) => type === 'inbound'
        ? `<div class="return-qty-grid"><span>Diterima <strong>${numberText(row.qty_received)}</strong></span><span>Resi <strong>${numberText(row.qty_expected)}</strong></span><span class="text-success">Bagus <strong>${numberText(row.qty_good)}</strong></span><span class="text-danger">Rusak <strong>${numberText(row.qty_damaged)}</strong></span>${Number(row.qty_difference) > 0 ? `<span class="text-warning">Selisih <strong>${numberText(row.qty_difference)}</strong></span>` : ''}</div>`
        : `<div class="return-qty-grid"><span>Total <strong>${numberText(row.total_qty)}</strong></span><span class="text-success">Reguler <strong>${numberText(row.regular_qty)}</strong></span><span class="text-danger">Rusak <strong>${numberText(row.damaged_qty)}</strong></span></div>`;

    const reasonList = (items) => {
        const reasons = [...new Set((items || []).map((item) => item.reason).filter(Boolean))];
        return reasons.length
            ? reasons.slice(0, 3).map((reason) => `<span class="badge badge-light-secondary me-1 mb-1">${esc(reason)}</span>`).join('')
            : '<span class="text-muted">-</span>';
    };

    const referenceCell = (row) => `<div class="return-code">${esc(row.code)}</div><div class="text-muted fs-8 mt-1">Ref: ${esc(row.ref_no || '-')}</div>`;
    const operatorCell = (row, type) => `<div class="fw-semibold text-gray-700">${esc(row.submit_by)}</div><div class="text-muted fs-8 mt-1">${type === 'inbound' && row.status === 'finalized' ? `Final: ${esc(row.finalized_by)}` : `Approve: ${esc(row.approved_by)}`}</div>`;
    const detailButton = (row) => `<a href="${esc(row.detail_url)}" class="btn btn-sm btn-icon btn-light-primary" title="Buka detail transaksi"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>`;

    function columnsFor(type) {
        if (type === 'inbound') {
            return [
                { data: 'transacted_at', render: (value) => `<div class="fw-semibold text-nowrap">${formatDate(value)}</div><div class="text-muted fs-8">${esc((value || '').slice(11, 16))}</div>` },
                { data: null, render: (row) => referenceCell(row) },
                { data: null, render: (row) => `<div class="fw-semibold">${esc(row.return_resi_no)}</div><div class="text-muted fs-8 mt-1">Order: ${esc(row.order_no)}</div>` },
                { data: 'items', render: (items) => itemList(items, type) },
                { data: null, render: (row) => qtyGrid(row, type) },
                { data: 'items', render: reasonList },
                { data: 'status', render: statusBadge },
                { data: null, render: (row) => operatorCell(row, type) },
                { data: null, className: 'text-end', render: detailButton },
            ];
        }
        return [
            { data: 'transacted_at', render: (value) => `<div class="fw-semibold text-nowrap">${formatDate(value)}</div><div class="text-muted fs-8">${esc((value || '').slice(11, 16))}</div>` },
            { data: null, render: (row) => referenceCell(row) },
            { data: 'items', render: (items) => itemList(items, type) },
            { data: null, render: (row) => qtyGrid(row, type) },
            { data: 'status', render: statusBadge },
            { data: null, render: (row) => operatorCell(row, type) },
            { data: 'note', render: (value) => `<div class="text-muted text-truncate" style="max-width:190px" title="${esc(value)}">${esc(value || '-')}</div>` },
            { data: null, className: 'text-end', render: detailButton },
        ];
    }

    function filters(panel, type) {
        return {
            q: panel.querySelector('.report-search').value.trim(),
            date_from: panel.querySelector('.report-date-from').value,
            date_to: panel.querySelector('.report-date-to').value,
            status: panel.querySelector('.report-status').value,
            [type === 'inbound' ? 'reason_id' : 'stock_source']: panel.querySelector('.report-extra-filter').value,
        };
    }

    function renderRanks(container, rows, suffix = 'qty', secondaryLabel = '') {
        if (!Array.isArray(rows) || !rows.length) {
            container.innerHTML = emptyHtml();
            return;
        }
        const max = Math.max(...rows.map((row) => Number(row.value || 0)), 1);
        container.innerHTML = rows.map((row) => {
            const secondary = secondaryLabel && Number(row.secondary || 0) > 0 ? ` · ${numberText(row.secondary)} ${secondaryLabel}` : '';
            const description = row.description ? `<div class="text-muted fs-8 text-truncate" title="${esc(row.description)}">${esc(row.description)}</div>` : '';
            return `<div class="rank-row"><div class="rank-meta"><div style="min-width:0"><div class="rank-label" title="${esc(row.label)}">${esc(row.label)}</div>${description}</div><strong>${numberText(row.value)} ${esc(suffix)}${secondary}</strong></div><div class="rank-track"><div class="rank-fill" style="width:${Math.max(3, (Number(row.value || 0) / max) * 100)}%"></div></div></div>`;
        }).join('');
    }

    function renderTrend(panel, type, rows) {
        const container = panel.querySelector('.report-trend');
        if (!Array.isArray(rows) || !rows.length) {
            container.innerHTML = emptyHtml();
            return;
        }
        const config = configs[type];
        const max = Math.max(...rows.flatMap((row) => [Number(row[config.primaryDaily] || 0), Number(row[config.secondaryDaily] || 0)]), 1);
        container.innerHTML = rows.map((row) => {
            const primary = Number(row[config.primaryDaily] || 0);
            const secondary = Number(row[config.secondaryDaily] || 0);
            const tooltip = `${formatDate(row.date)} · ${numberText(primary)} utama · ${numberText(secondary)} rusak`;
            return `<div class="return-day" title="${esc(tooltip)}"><div class="return-day-bars"><div class="return-day-bar" style="height:${Math.max(3, (primary / max) * 100)}%;background:${config.primaryColor}"></div><div class="return-day-bar" style="height:${Math.max(3, (secondary / max) * 100)}%;background:${config.secondaryColor}"></div></div><div class="return-day-label">${formatDate(row.date, false)}</div></div>`;
        }).join('');
    }

    function setKpi(panel, key, value) {
        const element = panel.querySelector(`[data-kpi="${key}"]`);
        if (element) element.textContent = numberText(value);
    }

    function setNote(panel, key, value) {
        const element = panel.querySelector(`[data-note="${key}"]`);
        if (element) element.textContent = value;
    }

    function updateDashboard(panel, type, json) {
        const summary = json.summary || {};
        const analytics = json.analytics || {};
        Object.entries(summary).forEach(([key, value]) => setKpi(panel, key, value));

        if (type === 'inbound') {
            setNote(panel, 'transactions', `${numberText(summary.distinct_sku)} SKU unik · ${numberText(summary.linked_resi)} terhubung resi`);
            setNote(panel, 'received_qty', `Tingkat terima ${percentText(summary.received_rate)}`);
            setNote(panel, 'good_qty', `${percentText(summary.good_rate)} dari diterima`);
            setNote(panel, 'damaged_qty', `${percentText(summary.damaged_rate)} dari diterima`);
            panel.querySelector('.report-insight').innerHTML = `<strong>${numberText(summary.approved)} transaksi</strong> masih di Gudang Retur (${numberText(summary.waiting_stock_qty)} qty menunggu finalisasi). Dari transaksi selesai, <strong>${numberText(summary.stocked_good_qty)} qty</strong> masuk stok reguler dan <strong>${numberText(summary.stocked_damaged_qty)} qty</strong> masuk stok rusak. Tingkat finalisasi ${percentText(summary.finalization_rate)}.`;
            renderRanks(panel.querySelector('.report-breakdown'), analytics.reasons, 'qty');
            renderRanks(panel.querySelector('.report-top-skus'), analytics.top_skus, 'qty', 'rusak');
        } else {
            setNote(panel, 'transactions', `${numberText(summary.distinct_sku)} SKU unik · approval ${percentText(summary.approval_rate)}`);
            setNote(panel, 'damaged_qty', `${percentText(summary.damaged_source_rate)} dari total qty`);
            setNote(panel, 'issued_qty', `${numberText(summary.approved)} transaksi approved`);
            setNote(panel, 'waiting_approval_qty', `${numberText(summary.pending)} transaksi pending`);
            panel.querySelector('.report-insight').innerHTML = `<strong>${numberText(summary.waiting_approval_qty)} qty</strong> pada ${numberText(summary.pending)} transaksi masih menunggu approval. Sebanyak <strong>${numberText(summary.issued_qty)} qty</strong> sudah mengurangi stok setelah disetujui; ${percentText(summary.damaged_source_rate)} berasal dari stok rusak.`;
            renderRanks(panel.querySelector('.report-breakdown'), analytics.sources, 'qty');
            renderRanks(panel.querySelector('.report-top-skus'), analytics.top_skus, 'qty', 'dari stok rusak');
        }
        renderTrend(panel, type, analytics.daily || []);
        renderRanks(panel.querySelector('.report-submitters'), analytics.submitters, 'transaksi');
        const period = json.period || {};
        panel.querySelector('.report-updated').textContent = `Periode ${formatDate(period.from)} – ${formatDate(period.to)} · diperbarui ${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
    }

    function setDateRange(panel, range) {
        const to = new Date();
        const from = new Date();
        if (range === 'month') from.setDate(1);
        else from.setDate(to.getDate() - (Number(range) - 1));
        const localDate = (date) => {
            const shifted = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
            return shifted.toISOString().slice(0, 10);
        };
        panel.querySelector('.report-date-from').value = localDate(from);
        panel.querySelector('.report-date-to').value = localDate(to);
    }

    Object.keys(configs).forEach((type) => {
        const panel = document.querySelector(`[data-report="${type}"]`);
        const errorBox = panel.querySelector('.report-error');
        const table = $(panel.querySelector('.report-table')).DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searchDelay: 350,
            pageLength: 10,
            dom: 'rtip',
            ajax: {
                url: configs[type].url,
                data: (request) => Object.assign(request, filters(panel, type)),
                dataSrc: (json) => {
                    errorBox.classList.add('d-none');
                    updateDashboard(panel, type, json || {});
                    return json.data || [];
                },
                error: (xhr) => {
                    const message = xhr.responseJSON?.message || 'Data laporan gagal dimuat. Silakan periksa filter dan coba lagi.';
                    errorBox.textContent = message;
                    errorBox.classList.remove('d-none');
                },
            },
            columns: columnsFor(type),
            language: {
                processing: 'Memuat laporan...',
                emptyTable: 'Tidak ada transaksi pada filter ini',
                zeroRecords: 'Data tidak ditemukan',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ transaksi',
                infoEmpty: 'Tidak ada transaksi',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
            },
        });
        configs[type].table = table;

        const reload = () => table.ajax.reload(null, true);
        panel.querySelector('.report-apply').addEventListener('click', reload);
        panel.querySelector('.report-search').addEventListener('keydown', (event) => {
            if (event.key === 'Enter') reload();
        });
        panel.querySelector('.report-limit').addEventListener('change', (event) => table.page.len(Number(event.target.value)).draw());
        panel.querySelectorAll('.report-range').forEach((button) => button.addEventListener('click', () => {
            setDateRange(panel, button.dataset.days);
            reload();
        }));
        panel.querySelector('.report-reset').addEventListener('click', () => {
            panel.querySelector('.report-search').value = '';
            panel.querySelector('.report-date-from').value = defaultFrom;
            panel.querySelector('.report-date-to').value = defaultTo;
            panel.querySelector('.report-status').value = '';
            panel.querySelector('.report-extra-filter').value = '';
            panel.querySelector('.report-limit').value = '10';
            table.page.len(10);
            reload();
        });
    });

    document.querySelectorAll('[data-report-tab]').forEach((tab) => tab.addEventListener('shown.bs.tab', () => {
        const type = tab.dataset.reportTab;
        configs[type]?.table?.columns.adjust();
        window.history.replaceState(null, '', `#${type}`);
    }));

    const initialTab = window.location.hash.replace('#', '');
    if (configs[initialTab] && initialTab !== 'inbound') {
        bootstrap.Tab.getOrCreateInstance(document.querySelector(`[data-report-tab="${initialTab}"]`)).show();
    }
});
</script>
@endpush
