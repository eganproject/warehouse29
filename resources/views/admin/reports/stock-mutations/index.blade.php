@extends('layouts.admin')

@section('title', 'Laporan Mutasi Stok')
@section('page_title', 'Laporan Mutasi Stok')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <input type="text" class="form-control form-control-solid w-250px" id="report_search" placeholder="Cari SKU / sumber / kode">
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" value="{{ $today }}" placeholder="Dari">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" value="{{ $today }}" placeholder="Sampai">
                <select id="filter_source_type" class="form-select form-select-solid w-175px">
                    <option value="">Semua Sumber</option>
                    <option value="inbound">Inbound</option>
                    <option value="outbound">Outbound</option>
                    <option value="opname">Opname</option>
                    <option value="adjustment">Adjustment</option>
                    <option value="damaged">Damaged</option>
                    <option value="qc">QC</option>
                    <option value="picker">Picker</option>
                </select>
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
            <div class="col-md-2"><div class="p-4 bg-light rounded"><div class="text-muted small">Mutasi</div><div class="fs-3 fw-bolder" id="sum_mutations">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light rounded"><div class="text-muted small">SKU</div><div class="fs-3 fw-bolder" id="sum_sku">0</div></div></div>
            <div class="col-md-3"><div class="p-4 bg-light-success rounded"><div class="text-muted small">Qty Masuk</div><div class="fs-3 fw-bolder text-success" id="sum_in">0</div></div></div>
            <div class="col-md-3"><div class="p-4 bg-light-danger rounded"><div class="text-muted small">Qty Keluar</div><div class="fs-3 fw-bolder text-danger" id="sum_out">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-primary rounded"><div class="text-muted small">Net Qty</div><div class="fs-3 fw-bolder" id="sum_net">0</div></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6"><h3 class="card-title fw-bolder">Ringkasan Per Sumber</h3></div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase">
                        <th>Sumber</th>
                        <th>Subtipe</th>
                        <th>Arah</th>
                        <th class="text-end">Mutasi</th>
                        <th class="text-end">SKU</th>
                        <th class="text-end">Total Qty</th>
                        <th>Awal</th>
                        <th>Terakhir</th>
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
        const sourceTypeEl = document.getElementById('filter_source_type');
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
            document.getElementById('sum_mutations').textContent = summary.total_mutations ?? 0;
            document.getElementById('sum_sku').textContent = summary.total_sku ?? 0;
            document.getElementById('sum_in').textContent = summary.total_in ?? 0;
            document.getElementById('sum_out').textContent = summary.total_out ?? 0;
            document.getElementById('sum_net').textContent = summary.net_qty ?? 0;
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
                    params.source_type = sourceTypeEl?.value || '';
                }
            },
            columns: [
                { data: 'source_type' },
                { data: 'source_subtype' },
                { data: 'direction', render: (data) => `<span class="badge ${data === 'IN' ? 'badge-light-success' : 'badge-light-danger'}">${data}</span>` },
                { data: 'mutation_count', className: 'text-end' },
                { data: 'sku_count', className: 'text-end' },
                { data: 'total_qty', className: 'text-end fw-bold' },
                { data: 'first_at' },
                { data: 'last_at' },
            ]
        });

        const reload = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', (e) => { if (e.key === 'Enter') reload(); });
        applyBtn?.addEventListener('click', reload);
        sourceTypeEl?.addEventListener('change', reload);
        limitEl?.addEventListener('change', () => dt.page.len(Number(limitEl.value || 10)).draw());
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (sourceTypeEl) sourceTypeEl.value = '';
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
