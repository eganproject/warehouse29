@extends('layouts.admin')

@section('title', 'Transit')
@section('page_title', 'Transit')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date" placeholder="Tanggal" value="{{ $today ?? '' }}" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row g-4 mb-6">
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Picker Transit - Dalam Proses</div>
                        <div class="fs-2 fw-bold" id="picker_summary_ongoing">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Picker Transit - Selesai</div>
                        <div class="fs-2 fw-bold" id="picker_summary_done">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Packer Transit - Menunggu Scan Out</div>
                        <div class="fs-2 fw-bold" id="packer_summary_pending">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Packer Transit - Selesai</div>
                        <div class="fs-2 fw-bold" id="packer_summary_done">0</div>
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
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const dataUrlPacker = '{{ $dataUrlPacker }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picker_transit_table');
        const packerTableEl = $('#packer_transit_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateEl = document.getElementById('filter_date');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        let fpDate = null;
        let dtPicker = null;
        let dtPacker = null;

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof flatpickr !== 'undefined') {
            if (dateEl) {
                fpDate = flatpickr(dateEl, { dateFormat: 'Y-m-d', allowInput: true });
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
                    params.q = searchInput?.value || '';
                    if (dateEl?.value) params.date = dateEl.value;
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'date' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'qty', className: 'text-end' },
                { data: 'remaining_qty', className: 'text-end' },
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
                    params.q = searchInput?.value || '';
                    if (dateEl?.value) params.date = dateEl.value;
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

        const reloadAll = () => {
            dtPicker?.ajax?.reload();
            dtPacker?.ajax?.reload();
        };

        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach((el) => {
            el.addEventListener('shown.bs.tab', () => {
                dtPicker?.columns?.adjust();
                dtPacker?.columns?.adjust();
            });
        });

        searchInput?.addEventListener('keyup', reloadAll);
        filterApplyBtn?.addEventListener('click', reloadAll);
        filterResetBtn?.addEventListener('click', () => {
            if (fpDate && todayStr) {
                fpDate.setDate(todayStr, true);
            } else if (dateEl) {
                dateEl.value = todayStr || '';
            }
            reloadAll();
        });
    });
</script>
@endpush
