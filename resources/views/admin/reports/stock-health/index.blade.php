@extends('layouts.admin')

@section('title', 'Stock Health Report')
@section('page_title', 'Stock Health Report')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <input type="text" class="form-control form-control-solid w-250px" id="report_search" placeholder="Cari SKU / nama / alamat">
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <select id="filter_category" class="form-select form-select-solid w-200px">
                    <option value="">Semua Kategori</option>
                    <option value="0">Tanpa Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <select id="filter_status" class="form-select form-select-solid w-175px">
                    <option value="">Semua Status</option>
                    <option value="out">Out of Stock</option>
                    <option value="low">Low Stock</option>
                    <option value="healthy">Healthy</option>
                </select>
                <select id="filter_limit" class="form-select form-select-solid w-100px">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="row g-4">
            <div class="col-md-2"><div class="p-4 bg-light rounded"><div class="text-muted small">Total SKU</div><div class="fs-3 fw-bolder" id="sum_total_sku">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light rounded"><div class="text-muted small">Total Stok</div><div class="fs-3 fw-bolder" id="sum_total_stock">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-danger rounded"><div class="text-muted small">Out</div><div class="fs-3 fw-bolder text-danger" id="sum_out">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-warning rounded"><div class="text-muted small">Low</div><div class="fs-3 fw-bolder text-warning" id="sum_low">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-success rounded"><div class="text-muted small">Healthy</div><div class="fs-3 fw-bolder text-success" id="sum_healthy">0</div></div></div>
            <div class="col-md-2"><div class="p-4 bg-light-primary rounded"><div class="text-muted small">No Safety</div><div class="fs-3 fw-bolder" id="sum_no_safety">0</div></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6"><h3 class="card-title fw-bolder">Detail Kesehatan Stok</h3></div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase">
                        <th>SKU</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th class="text-end">Stok</th>
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

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#report_table');
        const searchInput = document.getElementById('report_search');
        const categoryEl = document.getElementById('filter_category');
        const statusEl = document.getElementById('filter_status');
        const limitEl = document.getElementById('filter_limit');
        const resetBtn = document.getElementById('filter_reset');

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(categoryEl).select2({ placeholder: 'Semua Kategori', allowClear: true, width: '100%' });
            $(statusEl).select2({ placeholder: 'Semua Status', allowClear: true, width: '100%' });
        }

        const setSummary = (summary = {}) => {
            document.getElementById('sum_total_sku').textContent = summary.total_sku ?? 0;
            document.getElementById('sum_total_stock').textContent = summary.total_stock ?? 0;
            document.getElementById('sum_out').textContent = summary.out_of_stock ?? 0;
            document.getElementById('sum_low').textContent = summary.low_stock ?? 0;
            document.getElementById('sum_healthy').textContent = summary.healthy_stock ?? 0;
            document.getElementById('sum_no_safety').textContent = summary.no_safety_stock ?? 0;
        };

        const statusBadge = (status) => {
            const map = {
                'Out of Stock': 'badge-light-danger',
                'Low Stock': 'badge-light-warning',
                'Healthy': 'badge-light-success',
                'No Safety Stock': 'badge-light-primary',
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
                data: (params) => {
                    params.q = searchInput?.value || '';
                    params.category_id = categoryEl?.value || '';
                    params.status = statusEl?.value || '';
                }
            },
            columns: [
                { data: 'sku', className: 'fw-bold' },
                { data: 'name' },
                { data: 'category' },
                { data: 'stock', className: 'text-end fw-bold' },
                { data: 'safety_stock', className: 'text-end' },
                { data: 'gap', className: 'text-end' },
                { data: 'status', render: statusBadge },
                { data: 'inbound_qty', className: 'text-end' },
                { data: 'outbound_qty', className: 'text-end' },
                { data: 'last_mutation_at' },
                { data: 'address' },
            ]
        });

        const reload = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', (e) => { if (e.key === 'Enter') reload(); });
        categoryEl?.addEventListener('change', reload);
        statusEl?.addEventListener('change', reload);
        limitEl?.addEventListener('change', () => dt.page.len(Number(limitEl.value || 10)).draw());
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (categoryEl) {
                categoryEl.value = '';
                if (typeof $ !== 'undefined' && $(categoryEl).data('select2')) $(categoryEl).val('').trigger('change.select2');
            }
            if (statusEl) {
                statusEl.value = '';
                if (typeof $ !== 'undefined' && $(statusEl).data('select2')) $(statusEl).val('').trigger('change.select2');
            }
            if (limitEl) {
                limitEl.value = '10';
                dt.page.len(10).draw();
            }
            reload();
        });
    });
</script>
@endpush
