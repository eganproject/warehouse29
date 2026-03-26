@extends('layouts.admin')

@section('title', 'Transit')
@section('page_title', 'Transit')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="fw-bold">Transit</div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row g-4 mb-6">
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="text-muted">Picker Transit - Dalam Proses</div>
                                <div class="fs-2 fw-bold" id="picker_summary_ongoing">0</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light btn-picker-status" data-status="ongoing" data-title="Picker Transit - Dalam Proses">
                                Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="text-muted">Picker Transit - Selesai</div>
                                <div class="fs-2 fw-bold" id="picker_summary_done">0</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light btn-picker-status" data-status="done" data-title="Picker Transit - Selesai">
                                Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="text-muted">Packer Transit - Menunggu Scan Out</div>
                                <div class="fs-2 fw-bold" id="packer_summary_pending">0</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light btn-packer-status" data-status="pending" data-title="Packer Transit - Menunggu Scan Out">
                                Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="text-muted">Packer Transit - Selesai</div>
                                <div class="fs-2 fw-bold" id="packer_summary_done">0</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light btn-packer-status" data-status="done" data-title="Packer Transit - Selesai">
                                Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs nav-line-tabs mb-6" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab_picker_transit" role="tab">Picker Transit</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#tab_packer_transit" role="tab">Packer Transit</a>
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
                        <option value="ongoing">Dalam Proses</option>
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
                                <th>Last Picked</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_packer_transit" role="tabpanel">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                            </svg>
                        </span>
                        <input type="text" class="form-control form-control-solid w-250px ps-14" id="packer_filter_search" placeholder="Search ID Pesanan / Resi / Status" />
                    </div>
                    <input type="text" class="form-control form-control-solid w-150px" id="packer_filter_date" placeholder="Tanggal" value="{{ $today ?? '' }}" />
                    <select class="form-select form-select-solid w-175px" id="packer_filter_status">
                        <option value="">Semua Status</option>
                        <option value="pending">Menunggu Scan Out</option>
                        <option value="done">Selesai</option>
                    </select>
                    <button type="button" class="btn btn-light" id="packer_filter_apply">Filter</button>
                    <button type="button" class="btn btn-light" id="packer_filter_reset">Reset</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="packer_transit_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>Waktu Input</th>
                                <th>ID Pesanan</th>
                                <th>No Resi</th>
                                <th>Status</th>
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
                    <h2 class="fw-bolder mb-0" id="picker_status_title">Picker Transit</h2>
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
                                <th>Last Picked</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_packer_status" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-0" id="packer_status_title">Packer Transit</h2>
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
                    <input type="text" class="form-control form-control-solid w-250px" id="packer_status_search" placeholder="Search ID Pesanan / Resi" />
                    <button type="button" class="btn btn-light-primary" id="packer_status_export">Export Excel</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="packer_status_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>Waktu Input</th>
                                <th>ID Pesanan</th>
                                <th>No Resi</th>
                                <th>Status</th>
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
    const dataUrlPacker = '{{ $dataUrlPacker }}';
    const exportPickerUrl = '{{ route('admin.inventory.picker-transit.export-picker') }}';
    const exportPackerUrl = '{{ route('admin.inventory.picker-transit.export-packer') }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picker_transit_table');
        const packerTableEl = $('#packer_transit_table');
        const pickerStatusTableEl = $('#picker_status_table');
        const packerStatusTableEl = $('#packer_status_table');
        const pickerSearchInput = document.getElementById('picker_filter_search');
        const pickerDateEl = document.getElementById('picker_filter_date');
        const pickerApplyBtn = document.getElementById('picker_filter_apply');
        const pickerResetBtn = document.getElementById('picker_filter_reset');
        const pickerStatusEl = document.getElementById('picker_filter_status');
        const packerSearchInput = document.getElementById('packer_filter_search');
        const packerDateEl = document.getElementById('packer_filter_date');
        const packerApplyBtn = document.getElementById('packer_filter_apply');
        const packerResetBtn = document.getElementById('packer_filter_reset');
        const packerStatusEl = document.getElementById('packer_filter_status');
        const pickerStatusModalEl = document.getElementById('modal_picker_status');
        const packerStatusModalEl = document.getElementById('modal_packer_status');
        const pickerStatusModal = pickerStatusModalEl ? new bootstrap.Modal(pickerStatusModalEl) : null;
        const packerStatusModal = packerStatusModalEl ? new bootstrap.Modal(packerStatusModalEl) : null;
        const pickerStatusTitleEl = document.getElementById('picker_status_title');
        const packerStatusTitleEl = document.getElementById('packer_status_title');
        const pickerStatusSearchEl = document.getElementById('picker_status_search');
        const packerStatusSearchEl = document.getElementById('packer_status_search');
        const pickerStatusExportBtn = document.getElementById('picker_status_export');
        const packerStatusExportBtn = document.getElementById('packer_status_export');
        let fpPickerDate = null;
        let fpPackerDate = null;
        let dtPicker = null;
        let dtPacker = null;
        let dtPickerStatus = null;
        let dtPackerStatus = null;
        let pickerStatusFilter = '';
        let packerStatusFilter = '';

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof flatpickr !== 'undefined') {
            if (pickerDateEl) {
                fpPickerDate = flatpickr(pickerDateEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (packerDateEl) {
                fpPackerDate = flatpickr(packerDateEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
        }

        dtPicker = tableEl.DataTable({
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
                    params.q = pickerSearchInput?.value || '';
                    if (pickerDateEl?.value) params.date = pickerDateEl.value;
                    if (pickerStatusEl?.value) params.status = pickerStatusEl.value;
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

        dtPacker = packerTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[1, 'desc']],
            ajax: {
                url: dataUrlPacker,
                dataSrc: function(json) {
                    const summary = json?.summary || {};
                    const pending = summary.pending ?? 0;
                    const done = summary.done ?? 0;
                    const elPending = document.getElementById('packer_summary_pending');
                    const elDone = document.getElementById('packer_summary_done');
                    if (elPending) elPending.textContent = pending;
                    if (elDone) elDone.textContent = done;
                    return json.data || [];
                },
                data: function(params) {
                    params.q = packerSearchInput?.value || '';
                    if (packerDateEl?.value) params.date = packerDateEl.value;
                    if (packerStatusEl?.value) params.status = packerStatusEl.value;
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'created_at' },
                { data: 'id_pesanan' },
                { data: 'no_resi' },
                { data: 'status', render: (data) => {
                    const text = data || '-';
                    const normalized = String(text).toLowerCase();
                    let badgeClass = 'badge-light-secondary';
                    if (normalized === 'menunggu scan out') {
                        badgeClass = 'badge-light-warning';
                    } else if (normalized === 'selesai') {
                        badgeClass = 'badge-light-success';
                    }
                    return `<span class="badge ${badgeClass}">${text}</span>`;
                }},
            ]
        });

        const reloadPicker = () => dtPicker?.ajax?.reload();
        const reloadPacker = () => dtPacker?.ajax?.reload();

        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach((el) => {
            el.addEventListener('shown.bs.tab', () => {
                dtPicker?.columns?.adjust();
                dtPacker?.columns?.adjust();
            });
        });

        pickerSearchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadPicker();
        });
        pickerApplyBtn?.addEventListener('click', reloadPicker);
        pickerResetBtn?.addEventListener('click', () => {
            if (fpPickerDate && todayStr) {
                fpPickerDate.setDate(todayStr, true);
            } else if (pickerDateEl) {
                pickerDateEl.value = todayStr || '';
            }
            if (pickerSearchInput) pickerSearchInput.value = '';
            if (pickerStatusEl) pickerStatusEl.value = '';
            reloadPicker();
        });
        pickerStatusEl?.addEventListener('change', reloadPicker);

        packerSearchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadPacker();
        });
        packerApplyBtn?.addEventListener('click', reloadPacker);
        packerResetBtn?.addEventListener('click', () => {
            if (fpPackerDate && todayStr) {
                fpPackerDate.setDate(todayStr, true);
            } else if (packerDateEl) {
                packerDateEl.value = todayStr || '';
            }
            if (packerSearchInput) packerSearchInput.value = '';
            if (packerStatusEl) packerStatusEl.value = '';
            reloadPacker();
        });
        packerStatusEl?.addEventListener('change', reloadPacker);

        const initPickerStatusTable = () => {
            if (!pickerStatusTableEl.length || dtPickerStatus) return;
            dtPickerStatus = pickerStatusTableEl.DataTable({
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
                        params.q = pickerStatusSearchEl?.value || '';
                        if (pickerDateEl?.value) params.date = pickerDateEl.value;
                        if (pickerStatusFilter) params.status = pickerStatusFilter;
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

        const initPackerStatusTable = () => {
            if (!packerStatusTableEl.length || dtPackerStatus) return;
            dtPackerStatus = packerStatusTableEl.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                order: [[1, 'desc']],
                responsive: true,
                scrollX: true,
                ajax: {
                    url: dataUrlPacker,
                    dataSrc: 'data',
                    data: function(params) {
                        params.q = packerStatusSearchEl?.value || '';
                        if (packerDateEl?.value) params.date = packerDateEl.value;
                        if (packerStatusFilter) params.status = packerStatusFilter;
                    }
                },
                columns: [
                    { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                    { data: 'created_at' },
                    { data: 'id_pesanan' },
                    { data: 'no_resi' },
                    { data: 'status' },
                ]
            });
        };

        const openPickerStatus = (status, title) => {
            pickerStatusFilter = status || '';
            if (pickerStatusTitleEl) pickerStatusTitleEl.textContent = title || 'Picker Transit';
            if (pickerStatusSearchEl) pickerStatusSearchEl.value = '';
            initPickerStatusTable();
            dtPickerStatus?.ajax?.reload();
            pickerStatusModal?.show();
        };

        const openPackerStatus = (status, title) => {
            packerStatusFilter = status || '';
            if (packerStatusTitleEl) packerStatusTitleEl.textContent = title || 'Packer Transit';
            if (packerStatusSearchEl) packerStatusSearchEl.value = '';
            initPackerStatusTable();
            dtPackerStatus?.ajax?.reload();
            packerStatusModal?.show();
        };

        document.querySelectorAll('.btn-picker-status').forEach((btn) => {
            btn.addEventListener('click', () => {
                openPickerStatus(btn.getAttribute('data-status'), btn.getAttribute('data-title'));
            });
        });
        document.querySelectorAll('.btn-packer-status').forEach((btn) => {
            btn.addEventListener('click', () => {
                openPackerStatus(btn.getAttribute('data-status'), btn.getAttribute('data-title'));
            });
        });

        let pickerStatusTimer = null;
        pickerStatusSearchEl?.addEventListener('input', () => {
            if (pickerStatusTimer) clearTimeout(pickerStatusTimer);
            pickerStatusTimer = setTimeout(() => {
                dtPickerStatus?.ajax?.reload();
            }, 300);
        });
        pickerStatusExportBtn?.addEventListener('click', () => {
            const params = new URLSearchParams();
            const q = (pickerStatusSearchEl?.value || '').trim();
            if (q) params.set('q', q);
            if (pickerDateEl?.value) params.set('date', pickerDateEl.value);
            if (pickerStatusFilter) params.set('status', pickerStatusFilter);
            const url = params.toString() ? `${exportPickerUrl}?${params.toString()}` : exportPickerUrl;
            window.location.href = url;
        });
        let packerStatusTimer = null;
        packerStatusSearchEl?.addEventListener('input', () => {
            if (packerStatusTimer) clearTimeout(packerStatusTimer);
            packerStatusTimer = setTimeout(() => {
                dtPackerStatus?.ajax?.reload();
            }, 300);
        });
        packerStatusExportBtn?.addEventListener('click', () => {
            const params = new URLSearchParams();
            const q = (packerStatusSearchEl?.value || '').trim();
            if (q) params.set('q', q);
            if (packerDateEl?.value) params.set('date', packerDateEl.value);
            if (packerStatusFilter) params.set('status', packerStatusFilter);
            const url = params.toString() ? `${exportPackerUrl}?${params.toString()}` : exportPackerUrl;
            window.location.href = url;
        });

        pickerStatusModalEl?.addEventListener('shown.bs.modal', () => {
            dtPickerStatus?.columns?.adjust();
        });
        packerStatusModalEl?.addEventListener('shown.bs.modal', () => {
            dtPackerStatus?.columns?.adjust();
        });
    });
</script>
@endpush
