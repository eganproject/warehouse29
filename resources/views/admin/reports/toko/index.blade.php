@extends('layouts.admin')

@section('title', 'Laporan Toko')
@section('page_title', 'Laporan Toko')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bolder mb-1">Resi per Toko</h3>
            <div class="text-muted fs-7">Berdasarkan tanggal upload import resi. Resi tanpa kolom Nama Toko masuk ke "Tanpa Toko".</div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <input type="text" class="form-control form-control-solid w-200px" id="report_search" placeholder="Cari nama toko">
                <input type="text" class="form-control form-control-solid w-135px" id="filter_date_from" value="{{ $today }}" placeholder="Dari">
                <input type="text" class="form-control form-control-solid w-135px" id="filter_date_to" value="{{ $today }}" placeholder="Sampai">
                @include('admin.partials.toko-filter', ['tokos' => $tokos, 'id' => 'filter_toko', 'class' => 'w-175px'])
                @include('admin.partials.toko-filter', ['tokos' => $channels, 'id' => 'filter_channel', 'class' => 'w-150px', 'type' => 'channel'])
                <button type="button" class="btn btn-primary" id="filter_apply"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                <button type="button" class="btn btn-success" id="btn_export"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="row g-4">
            <div class="col-6 col-md-4 col-xl-2"><div class="p-4 bg-light rounded h-100"><div class="text-muted small">Toko Aktif</div><div class="fs-3 fw-bolder" id="sum_toko">0</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="p-4 bg-light-primary rounded h-100"><div class="text-muted small">Resi Aktif</div><div class="fs-3 fw-bolder" id="sum_resi">0</div><div class="text-muted fs-8" id="sum_qty">0 qty</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="p-4 bg-light-success rounded h-100"><div class="text-muted small">Sudah Scan Out</div><div class="fs-3 fw-bolder text-success" id="sum_scan">0</div><div class="text-muted fs-8" id="sum_rate">0% selesai</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="p-4 bg-light-warning rounded h-100"><div class="text-muted small">Sisa Belum Scan Out</div><div class="fs-3 fw-bolder text-warning" id="sum_remaining">0</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="p-4 bg-light-danger rounded h-100"><div class="text-muted small">Resi Batal</div><div class="fs-3 fw-bolder text-danger" id="sum_canceled">0</div><div class="text-muted fs-8" id="sum_cancel_rate">0% rasio batal</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="p-4 bg-light-info rounded h-100"><div class="text-muted small">Toko Tersibuk</div><div class="fs-5 fw-bolder text-truncate" id="sum_top">-</div><div class="text-muted fs-8" id="sum_top_resi">0 resi</div></div></div>
        </div>
        <div class="text-muted fs-8 mt-3 d-none" id="without_toko_note"></div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6"><h3 class="card-title fw-bolder">Rekap per Toko</h3></div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4" id="report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase">
                        <th>Toko</th>
                        <th class="text-end">Resi Aktif</th>
                        <th class="text-end">Sudah QC</th>
                        <th class="text-end">Scan Out</th>
                        <th class="text-end">Sisa</th>
                        <th style="min-width:140px">Penyelesaian</th>
                        <th class="text-end">Batal</th>
                        <th class="text-end">Qty</th>
                        <th>Scan Out Terakhir</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const exportUrl = '{{ $exportUrl }}';
    const todayStr = '{{ $today }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#report_table');
        const searchInput = document.getElementById('report_search');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const tokoEl = document.getElementById('filter_toko');
        const channelEl = document.getElementById('filter_channel');
        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        const num = (v) => Number(v || 0).toLocaleString('id-ID');
        let fpFrom = null;
        let fpTo = null;

        if (typeof flatpickr !== 'undefined') {
            fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        const filterParams = () => ({
            q: searchInput?.value || '',
            date_from: dateFromEl?.value || '',
            date_to: dateToEl?.value || '',
            toko_id: tokoEl?.value || '',
            channel_id: channelEl?.value || '',
        });

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        const setSummary = (summary = {}) => {
            setText('sum_toko', num(summary.toko_count));
            setText('sum_resi', num(summary.total_resi));
            setText('sum_qty', `${num(summary.total_qty)} qty`);
            setText('sum_scan', num(summary.scan_total));
            setText('sum_rate', `${summary.completion_rate ?? 0}% selesai`);
            setText('sum_remaining', num(summary.remaining));
            setText('sum_canceled', num(summary.canceled_total));
            setText('sum_cancel_rate', `${summary.cancel_rate ?? 0}% rasio batal`);
            setText('sum_top', summary.top_toko || '-');
            setText('sum_top_resi', `${num(summary.top_toko_resi)} resi`);
            const note = document.getElementById('without_toko_note');
            if (note) {
                const without = Number(summary.without_toko || 0);
                note.classList.toggle('d-none', without === 0);
                note.textContent = without > 0 ? `${num(without)} resi aktif belum memiliki informasi toko (import tanpa kolom Nama Toko).` : '';
            }
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: false,
            searching: false,
            dom: 'rtip',
            pageLength: 25,
            order: [[1, 'desc']],
            ajax: {
                url: dataUrl,
                data: (params) => Object.assign(params, filterParams()),
                dataSrc: (json) => {
                    setSummary(json?.summary || {});
                    return json?.data || [];
                },
            },
            language: { emptyTable: 'Tidak ada resi pada periode ini.' },
            columns: [
                { data: 'toko', render: (data, type, row) => {
                    if (type !== 'display') return data;
                    const muted = row.toko_id === null ? ' text-muted fst-italic' : '';
                    const channels = row.channels && row.channels !== '-' ? `<div class="text-muted fs-8">${esc(row.channels)}</div>` : '';
                    return `<span class="fw-bold${muted}">${esc(data)}</span>${channels}<div class="text-muted fs-9">${row.share}% dari total resi</div>`;
                }},
                { data: 'total_resi', className: 'text-end fw-bold', render: (d, type) => type === 'display' ? num(d) : d },
                { data: 'qc_total', className: 'text-end', render: (d, type) => type === 'display' ? num(d) : d },
                { data: 'scan_total', className: 'text-end text-success', render: (d, type) => type === 'display' ? num(d) : d },
                { data: 'remaining', className: 'text-end fw-bold', render: (d, type) => type === 'display'
                    ? `<span class="${Number(d) > 0 ? 'text-warning' : 'text-success'}">${num(d)}</span>` : d },
                { data: 'completion_rate', render: (d, type) => {
                    if (type !== 'display') return d;
                    const pct = Math.min(100, Number(d || 0));
                    const bar = pct >= 100 ? 'bg-success' : (pct >= 50 ? 'bg-primary' : 'bg-warning');
                    return `<div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1 h-6px bg-light"><div class="progress-bar ${bar}" style="width:${pct}%"></div></div>
                                <span class="fw-semibold fs-8">${esc(d)}%</span>
                            </div>`;
                }},
                { data: 'canceled_total', className: 'text-end', render: (d, type, row) => type === 'display'
                    ? `<span class="${Number(d) > 0 ? 'text-danger' : ''}">${num(d)}</span><div class="text-muted fs-9">${row.cancel_rate}%</div>` : d },
                { data: 'total_qty', className: 'text-end', render: (d, type, row) => type === 'display'
                    ? `${num(d)}<div class="text-muted fs-9">${row.avg_qty} / resi</div>` : d },
                { data: 'last_scan_at', render: (d, type, row) => type === 'display'
                    ? `${esc(d)}${row.first_scan_at && row.first_scan_at !== '-' ? `<div class="text-muted fs-9">mulai ${esc(row.first_scan_at)}</div>` : ''}` : d },
            ],
        });

        const reload = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', (e) => { if (e.key === 'Enter') reload(); });
        document.getElementById('filter_apply')?.addEventListener('click', reload);
        tokoEl?.addEventListener('change', reload);
        channelEl?.addEventListener('change', reload);
        document.getElementById('filter_reset')?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (tokoEl) tokoEl.value = '';
            if (channelEl) channelEl.value = '';
            if (fpFrom) fpFrom.setDate(todayStr, true); else dateFromEl.value = todayStr;
            if (fpTo) fpTo.setDate(todayStr, true); else dateToEl.value = todayStr;
            reload();
        });
        document.getElementById('btn_export')?.addEventListener('click', () => {
            window.location.href = `${exportUrl}?${new URLSearchParams(filterParams()).toString()}`;
        });
    });
</script>
@endpush
