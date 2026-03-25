@extends('layouts.admin')

@section('title', 'Picking List')
@section('page_title', 'Picking List')

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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search SKU / Nama" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date" placeholder="Tanggal" value="{{ $today ?? '' }}" />
                <select class="form-select form-select-solid w-175px" id="filter_status">
                    <option value="">Semua Status</option>
                    <option value="ongoing">Dalam Proses</option>
                    <option value="done">Selesai</option>
                </select>
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
                        <div class="text-muted">SKU Dalam Proses</div>
                        <div class="fs-2 fw-bold" id="summary_ongoing">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">SKU Selesai</div>
                        <div class="fs-2 fw-bold" id="summary_done">0</div>
                    </div>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs nav-line-tabs mb-6" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab_picking_list" role="tab">Picking List</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#tab_picking_exception" role="tab">Exception</a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_picking_list" role="tabpanel">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="picking_list_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Tanggal</th>
                                <th>SKU</th>
                                <th>Nama</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Remaining</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_picking_exception" role="tabpanel">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="picking_exception_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Tanggal</th>
                                <th>SKU</th>
                                <th>Nama</th>
                                <th class="text-end">Qty</th>
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
    const dataUrlExceptions = '{{ $dataUrlExceptions }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picking_list_table');
        const exceptionTableEl = $('#picking_exception_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateEl = document.getElementById('filter_date');
        const statusEl = document.getElementById('filter_status');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const summaryOngoingEl = document.getElementById('summary_ongoing');
        const summaryDoneEl = document.getElementById('summary_done');
        let fpDate = null;
        let dtList = null;
        let dtException = null;

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof flatpickr !== 'undefined') {
            if (dateEl) {
                fpDate = flatpickr(dateEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
        }

        dtList = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: function(json) {
                    const summary = json?.summary || {};
                    if (summaryOngoingEl) summaryOngoingEl.textContent = summary.ongoing ?? 0;
                    if (summaryDoneEl) summaryDoneEl.textContent = summary.done ?? 0;
                    return json.data || [];
                },
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (dateEl?.value) params.date = dateEl.value;
                    if (statusEl?.value) params.status = statusEl.value;
                }
            },
            columns: [
                { data: 'date' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'qty', className: 'text-end' },
                { data: 'remaining_qty', className: 'text-end' },
            ]
        });

        dtException = exceptionTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrlExceptions,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (dateEl?.value) params.date = dateEl.value;
                }
            },
            columns: [
                { data: 'date' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'qty', className: 'text-end' },
            ]
        });

        const reloadAll = () => {
            dtList?.ajax?.reload();
            dtException?.ajax?.reload();
        };

        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach((el) => {
            el.addEventListener('shown.bs.tab', () => {
                dtList?.columns?.adjust();
                dtException?.columns?.adjust();
            });
        });

        searchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadAll();
        });
        filterApplyBtn?.addEventListener('click', reloadAll);
        statusEl?.addEventListener('change', reloadAll);
        filterResetBtn?.addEventListener('click', () => {
            if (fpDate && todayStr) {
                fpDate.setDate(todayStr, true);
            } else if (dateEl) {
                dateEl.value = todayStr || '';
            }
            if (searchInput) searchInput.value = '';
            if (statusEl) statusEl.value = '';
            reloadAll();
        });
    });
</script>
@endpush
