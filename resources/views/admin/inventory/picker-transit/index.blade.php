@extends('layouts.admin')

@section('title', 'QC Transit')
@section('page_title', 'QC Transit')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="fw-bold">QC Transit</div>
        </div>
    </div>
        <div class="card-body py-6">
            <div class="row g-4 mb-6">
                <div class="col-md-6">
                    <div class="card card-flush h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="text-muted">QC Transit - Belum Scan Out</div>
                                    <div class="fs-2 fw-bold" id="picker_summary_ongoing">0</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light btn-picker-status" data-status="ongoing" data-title="QC Transit - Belum Scan Out">
                                    Detail
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-flush h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="text-muted">QC Transit - Selesai</div>
                                    <div class="fs-2 fw-bold" id="picker_summary_done">0</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light btn-picker-status" data-status="done" data-title="QC Transit - Selesai">
                                    Detail
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <ul class="nav nav-tabs nav-line-tabs mb-6" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tab_picker_transit" role="tab">QC Transit</a>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_picker_transit" role="tabpanel">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                            </svg>
                        </span>
                        <input type="text" class="form-control form-control-solid w-250px ps-14" id="picker_filter_search" placeholder="Search SKU / Nama" />
                    </div>
                    <input type="text" class="form-control form-control-solid w-150px" id="picker_filter_date" placeholder="Tanggal" value="{{ $today ?? '' }}" />
                        <select class="form-select form-select-solid w-175px" id="picker_filter_status">
                            <option value="">Semua Status</option>
                            <option value="ongoing">Belum Scan Out</option>
                            <option value="done">Selesai</option>
                        </select>
                    <button type="button" class="btn btn-light" id="picker_filter_apply">Filter</button>
                    <button type="button" class="btn btn-light" id="picker_filter_reset">Reset</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="picker_transit_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>SKU</th>
                                <th>Nama</th>
                                    <th class="text-end">Qty Transit</th>
                                    <th class="text-end">Sisa Qty</th>
                                    <th>Last QC</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>
</div>

<div class="modal fade" id="modal_picker_status" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                        <h2 class="fw-bolder mb-0" id="picker_status_title">QC Transit</h2>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <input type="text" class="form-control form-control-solid w-250px" id="picker_status_search" placeholder="Search SKU" />
                    <button type="button" class="btn btn-light-primary" id="picker_status_export">Export Excel</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="picker_status_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                    <th>Tanggal</th>
                                    <th>SKU</th>
                                    <th class="text-end">Qty Transit</th>
                                    <th class="text-end">Sisa Qty</th>
                                    <th>Last QC</th>
                                </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
        const dataUrl = '{{ $dataUrl }}';
        const exportUrl = '{{ route('admin.inventory.picker-transit.export-picker') }}';
        const todayStr = '{{ $today ?? '' }}';

        document.addEventListener('DOMContentLoaded', () => {
            const tableEl = $('#picker_transit_table');
            const statusTableEl = $('#picker_status_table');
            const searchInput = document.getElementById('picker_filter_search');
            const dateEl = document.getElementById('picker_filter_date');
            const applyBtn = document.getElementById('picker_filter_apply');
            const resetBtn = document.getElementById('picker_filter_reset');
            const statusEl = document.getElementById('picker_filter_status');
            const statusModalEl = document.getElementById('modal_picker_status');
            const statusModal = statusModalEl ? new bootstrap.Modal(statusModalEl) : null;
            const statusTitleEl = document.getElementById('picker_status_title');
            const statusSearchEl = document.getElementById('picker_status_search');
            const statusExportBtn = document.getElementById('picker_status_export');
            let fpDate = null;
            let dt = null;
            let dtStatus = null;
            let statusFilter = '';

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

            if (typeof flatpickr !== 'undefined' && dateEl) {
                fpDate = flatpickr(dateEl, { dateFormat: 'Y-m-d', allowInput: true });
            }

            dt = tableEl.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                order: [[1, 'desc']],
                ajax: {
                    url: dataUrl,
                dataSrc: function(json) {
                    const summary = json?.summary || {};
                    const ongoing = summary.ongoing ?? 0;
                    const done = summary.done ?? 0;
                    const elOngoing = document.getElementById('picker_summary_ongoing');
                    const elDone = document.getElementById('picker_summary_done');
                    if (elOngoing) elOngoing.textContent = ongoing;
                    if (elDone) elDone.textContent = done;
                    return json.data || [];
                    },
                    data: function(params) {
                        params.q = searchInput?.value || '';
                        if (dateEl?.value) params.date = dateEl.value;
                        if (statusEl?.value) params.status = statusEl.value;
                    }
                },
                columns: [
                    { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                    { data: 'date' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'qty', className: 'text-end' },
                { data: 'remaining_qty', className: 'text-end', render: (data) => {
                    const value = Number(data) || 0;
                    const badgeClass = value > 0 ? 'badge-light-warning' : 'badge-light-success';
                    return `<span class="badge ${badgeClass}">${value}</span>`;
                }},
                    { data: 'picked_at' },
                ]
            });

            const reload = () => dt?.ajax?.reload();

            searchInput?.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') reload();
            });
            applyBtn?.addEventListener('click', reload);
            resetBtn?.addEventListener('click', () => {
                if (fpDate && todayStr) {
                    fpDate.setDate(todayStr, true);
                } else if (dateEl) {
                    dateEl.value = todayStr || '';
                }
                if (searchInput) searchInput.value = '';
                if (statusEl) statusEl.value = '';
                reload();
            });
            statusEl?.addEventListener('change', reload);

            const initStatusTable = () => {
                if (!statusTableEl.length || dtStatus) return;
                dtStatus = statusTableEl.DataTable({
                    processing: true,
                    serverSide: true,
                    dom: 'rtip',
                    order: [[1, 'desc']],
                    responsive: true,
                    scrollX: true,
                    ajax: {
                        url: dataUrl,
                        dataSrc: 'data',
                        data: function(params) {
                            params.q = statusSearchEl?.value || '';
                            if (dateEl?.value) params.date = dateEl.value;
                            if (statusFilter) params.status = statusFilter;
                        }
                    },
                    columns: [
                        { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                        { data: 'date' },
                        { data: 'sku' },
                        { data: 'qty', className: 'text-end' },
                        { data: 'remaining_qty', className: 'text-end' },
                        { data: 'picked_at' },
                    ]
                });
            };

            const openStatus = (status, title) => {
                statusFilter = status || '';
                if (statusTitleEl) statusTitleEl.textContent = title || 'QC Transit';
                if (statusSearchEl) statusSearchEl.value = '';
                initStatusTable();
                dtStatus?.ajax?.reload();
                statusModal?.show();
            };

            document.querySelectorAll('.btn-picker-status').forEach((btn) => {
                btn.addEventListener('click', () => {
                    openStatus(btn.getAttribute('data-status'), btn.getAttribute('data-title'));
                });
            });

            let statusTimer = null;
            statusSearchEl?.addEventListener('input', () => {
                if (statusTimer) clearTimeout(statusTimer);
                statusTimer = setTimeout(() => {
                    dtStatus?.ajax?.reload();
                }, 300);
            });
            statusExportBtn?.addEventListener('click', () => {
                const params = new URLSearchParams();
                const q = (statusSearchEl?.value || '').trim();
                if (q) params.set('q', q);
                if (dateEl?.value) params.set('date', dateEl.value);
                if (statusFilter) params.set('status', statusFilter);
                const url = params.toString() ? `${exportUrl}?${params.toString()}` : exportUrl;
                window.location.href = url;
            });

            statusModalEl?.addEventListener('shown.bs.modal', () => {
                dtStatus?.columns?.adjust();
            });
    });
</script>
@endpush
