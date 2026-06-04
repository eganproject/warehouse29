@extends('layouts.admin')

@section('title', 'Laporan Kurir')
@section('page_title', 'Laporan Kurir')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <input type="text" class="form-control form-control-solid w-250px" id="report_search" placeholder="Cari kurir">
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" value="{{ $today }}" placeholder="Dari">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" value="{{ $today }}" placeholder="Sampai">
                <select id="filter_limit" class="form-select form-select-solid w-100px">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="row g-4">
            <div class="col-md-3"><div class="p-4 bg-light rounded"><div class="text-muted small">Total Resi</div><div class="fs-3 fw-bolder" id="sum_resi">0</div></div></div>
            <div class="col-md-3"><div class="p-4 bg-light-success rounded"><div class="text-muted small">Scan Out</div><div class="fs-3 fw-bolder text-success" id="sum_scan">0</div></div></div>
            <div class="col-md-3"><div class="p-4 bg-light-danger rounded"><div class="text-muted small">Remaining</div><div class="fs-3 fw-bolder text-danger" id="sum_remaining">0</div></div></div>
            <div class="col-md-3"><div class="p-4 bg-light-primary rounded"><div class="text-muted small">Completion</div><div class="fs-3 fw-bolder" id="sum_rate">0%</div></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6"><h3 class="card-title fw-bolder">Performa Kurir</h3></div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase">
                        <th>Kurir</th>
                        <th class="text-end">Resi Aktif</th>
                        <th class="text-end">Scan Out</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Canceled</th>
                        <th class="text-end">Completion</th>
                        <th>First Scan</th>
                        <th>Last Scan</th>
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
    const todayStr = '{{ $today }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#report_table');
        const searchInput = document.getElementById('report_search');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const limitEl = document.getElementById('filter_limit');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        let fpFrom = null;
        let fpTo = null;

        if (typeof flatpickr !== 'undefined') {
            fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        const setSummary = (summary = {}) => {
            document.getElementById('sum_resi').textContent = summary.total_resi ?? 0;
            document.getElementById('sum_scan').textContent = summary.scan_total ?? 0;
            document.getElementById('sum_remaining').textContent = summary.remaining ?? 0;
            document.getElementById('sum_rate').textContent = `${summary.completion_rate ?? 0}%`;
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            pageLength: Number(limitEl?.value || 10),
            ajax: {
                url: dataUrl,
                dataSrc: (json) => {
                    setSummary(json?.summary || {});
                    return json.data || [];
                },
                data: (params) => {
                    params.q = searchInput?.value || '';
                    params.date_from = dateFromEl?.value || '';
                    params.date_to = dateToEl?.value || '';
                }
            },
            columns: [
                { data: 'name', className: 'fw-bold' },
                { data: 'total_resi', className: 'text-end' },
                { data: 'scan_total', className: 'text-end' },
                { data: 'remaining', className: 'text-end fw-bold', render: (data) => `<span class="${Number(data) > 0 ? 'text-danger' : 'text-success'}">${data}</span>` },
                { data: 'canceled_total', className: 'text-end' },
                { data: 'completion_rate', className: 'text-end', render: (data) => `${data}%` },
                { data: 'first_scan_at' },
                { data: 'last_scan_at' },
            ]
        });

        const reload = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', (e) => { if (e.key === 'Enter') reload(); });
        applyBtn?.addEventListener('click', reload);
        limitEl?.addEventListener('change', () => dt.page.len(Number(limitEl.value || 10)).draw());
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (fpFrom) fpFrom.setDate(todayStr, true); else dateFromEl.value = todayStr;
            if (fpTo) fpTo.setDate(todayStr, true); else dateToEl.value = todayStr;
            if (limitEl) {
                limitEl.value = '10';
                dt.page.len(10).draw();
            }
            reload();
        });
    });
</script>
@endpush
