@extends('layouts.admin')

@section('title', 'Laporan Stok Per Tanggal')
@section('page_title', 'Laporan Stok Per Tanggal')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <input type="text" class="form-control form-control-solid w-250px" id="report_search" placeholder="Cari SKU / nama / alamat">
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_as_of_date" value="{{ $today }}" placeholder="Tanggal">
                <select id="filter_category" class="form-select form-select-solid w-200px">
                    <option value="">Semua Kategori</option>
                    <option value="0">Tanpa Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <select id="filter_stock_type" class="form-select form-select-solid w-175px">
                    <option value="regular">Filter Stok Reguler</option>
                    <option value="damaged">Filter Stok Rusak</option>
                </select>
                <select id="filter_status" class="form-select form-select-solid w-175px">
                    <option value="">Semua Status</option>
                    <option value="positive">Stok &gt; 0</option>
                    <option value="zero">Stok = 0</option>
                    <option value="negative">Stok &lt; 0</option>
                    <option value="low">Di bawah safety</option>
                </select>
                <select id="filter_limit" class="form-select form-select-solid w-100px">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <label class="form-check form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" id="filter_include_zero" checked>
                    <span class="form-check-label">Stok nol</span>
                </label>
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                <button type="button" class="btn btn-light-success" id="btn_export_report">
                    <i class="fa-solid fa-file-excel"></i> Export
                </button>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="row g-4">
            <div class="col-md-2"><div class="p-4 bg-light rounded"><div class="text-muted small">Total SKU</div><div class="fs-3 fw-bolder" id="sum_total_sku">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-primary rounded"><div class="text-muted small">Stok Reguler</div><div class="fs-3 fw-bolder" id="sum_regular">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-warning rounded"><div class="text-muted small">Stok Rusak</div><div class="fs-3 fw-bolder" id="sum_damaged">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-success rounded"><div class="text-muted small">Total Stok</div><div class="fs-3 fw-bolder" id="sum_all">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-danger rounded"><div class="text-muted small">Di bawah Safety</div><div class="fs-3 fw-bolder text-danger" id="sum_low">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light rounded"><div class="text-muted small">Stok Nol</div><div class="fs-3 fw-bolder" id="sum_zero">0</div></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title fw-bolder">Detail Stok Per Tanggal</h3>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase">
                        <th>SKU</th>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Kategori</th>
                        <th class="text-end">Stok Reguler</th>
                        <th class="text-end">Stok Rusak</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Safety</th>
                        <th class="text-end">Gap</th>
                        <th>Status</th>
                        <th class="text-end">IN</th>
                        <th class="text-end">OUT</th>
                        <th>Mutasi Terakhir</th>
                        <th>Alamat</th>
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
        const asOfDateEl = document.getElementById('filter_as_of_date');
        const categoryEl = document.getElementById('filter_category');
        const stockTypeEl = document.getElementById('filter_stock_type');
        const statusEl = document.getElementById('filter_status');
        const limitEl = document.getElementById('filter_limit');
        const includeZeroEl = document.getElementById('filter_include_zero');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const exportBtn = document.getElementById('btn_export_report');
        let fpDate = null;

        if (typeof flatpickr !== 'undefined') {
            fpDate = flatpickr(asOfDateEl, { dateFormat: 'Y-m-d', allowInput: true });
        }
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(categoryEl).select2({ placeholder: 'Semua Kategori', allowClear: true, width: '100%' });
            $(stockTypeEl).select2({ minimumResultsForSearch: Infinity, width: '100%' });
            $(statusEl).select2({ placeholder: 'Semua Status', allowClear: true, width: '100%' });
        }

        const numberText = (value) => Number(value || 0).toLocaleString('id-ID');
        const params = () => ({
            q: searchInput?.value || '',
            as_of_date: asOfDateEl?.value || todayStr,
            category_id: categoryEl?.value || '',
            stock_type: stockTypeEl?.value || 'regular',
            status: statusEl?.value || '',
            include_zero: includeZeroEl?.checked ? 1 : 0,
        });

        const setSummary = (summary = {}) => {
            document.getElementById('sum_total_sku').textContent = numberText(summary.total_sku);
            document.getElementById('sum_regular').textContent = numberText(summary.total_regular);
            document.getElementById('sum_damaged').textContent = numberText(summary.total_damaged);
            document.getElementById('sum_all').textContent = numberText(summary.total_all);
            document.getElementById('sum_low').textContent = numberText(summary.low_stock_sku);
            document.getElementById('sum_zero').textContent = numberText(summary.zero_regular_sku);
        };

        const statusBadge = (status) => {
            const map = {
                'Negative': 'badge-light-danger',
                'Zero': 'badge-light',
                'Low Stock': 'badge-light-warning',
                'Available': 'badge-light-success',
            };
            return `<span class="badge ${map[status] || 'badge-light'}">${status || '-'}</span>`;
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
                data: (requestParams) => Object.assign(requestParams, params()),
            },
            columns: [
                { data: 'sku', className: 'fw-bold' },
                { data: 'name' },
                { data: 'item_type' },
                { data: 'category' },
                { data: 'stock_as_of', className: 'text-end fw-bold', render: numberText },
                { data: 'damaged_stock_as_of', className: 'text-end', render: numberText },
                { data: 'total_stock_as_of', className: 'text-end fw-bold', render: numberText },
                { data: 'safety_stock', className: 'text-end', render: numberText },
                { data: 'gap', className: 'text-end', render: numberText },
                { data: 'status', render: statusBadge },
                { data: 'inbound_as_of', className: 'text-end', render: numberText },
                { data: 'outbound_as_of', className: 'text-end', render: numberText },
                { data: 'last_mutation_at' },
                { data: 'address' },
            ]
        });

        const reload = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', (e) => { if (e.key === 'Enter') reload(); });
        applyBtn?.addEventListener('click', reload);
        categoryEl?.addEventListener('change', reload);
        stockTypeEl?.addEventListener('change', reload);
        statusEl?.addEventListener('change', reload);
        includeZeroEl?.addEventListener('change', reload);
        limitEl?.addEventListener('change', () => dt.page.len(Number(limitEl.value || 10)).draw());
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (fpDate) fpDate.setDate(todayStr, true); else asOfDateEl.value = todayStr;
            if (categoryEl) {
                categoryEl.value = '';
                if (typeof $ !== 'undefined' && $(categoryEl).data('select2')) $(categoryEl).val('').trigger('change.select2');
            }
            if (stockTypeEl) {
                stockTypeEl.value = 'regular';
                if (typeof $ !== 'undefined' && $(stockTypeEl).data('select2')) $(stockTypeEl).val('regular').trigger('change.select2');
            }
            if (statusEl) {
                statusEl.value = '';
                if (typeof $ !== 'undefined' && $(statusEl).data('select2')) $(statusEl).val('').trigger('change.select2');
            }
            if (includeZeroEl) includeZeroEl.checked = true;
            if (limitEl) {
                limitEl.value = '10';
                dt.page.len(10).draw();
            }
            reload();
        });
        exportBtn?.addEventListener('click', () => {
            const query = new URLSearchParams(params()).toString();
            window.location.href = `${exportUrl}?${query}`;
        });
    });
</script>
@endpush
