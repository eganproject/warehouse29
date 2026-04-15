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
                    @if((auth()->user()->email ?? '') === 'admin@gmail.com')
                        <button type="button" class="btn btn-light-primary" id="picker_recalculate_today" title="Hitung ulang remaining picker transit untuk hari ini.">Recalculate Hari Ini</button>
                        <button type="button" class="btn btn-light" id="picker_audit_remaining" title="Audit SKU yang masih remaining dan indikasi penyebabnya.">Audit Remaining</button>
                    @endif
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

<div class="modal fade" id="modal_picker_transit_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="picker_transit_detail_title">Detail Resi</h5>
                    <div class="text-muted fs-7" id="picker_transit_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <div class="badge badge-light-primary">Total Resi: <span id="picker_transit_detail_total_resi">0</span></div>
                    <div class="badge badge-light-success">Total Qty SKU: <span id="picker_transit_detail_total_qty">0</span></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="10%">No</th>
                                <th width="45%">ID Pesanan</th>
                                <th width="30%">No Resi</th>
                                <th width="15%" class="text-end">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="picker_transit_detail_body">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_picker_transit_audit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Audit Remaining QC Transit</h5>
                    <div class="text-muted fs-7" id="picker_transit_audit_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>SKU</th>
                                <th>Nama</th>
                                <th class="text-end">Scanned</th>
                                <th class="text-end">Remaining</th>
                                <th>Exception</th>
                                <th>Indikasi</th>
                            </tr>
                        </thead>
                        <tbody id="picker_transit_audit_body">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
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
    const pickerTransitDetailUrl = '{{ route('admin.inventory.picker-transit.detail') }}';
    const pickerTransitAuditUrl = '{{ route('admin.inventory.picker-transit.audit') }}';
    const pickerTransitRecalcTodayUrl = '{{ route('admin.inventory.picker-transit.recalculate-today') }}';
    const csrfToken = '{{ csrf_token() }}';
    const exportPickerUrl = '{{ route('admin.inventory.picker-transit.export-picker') }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picker_transit_table');
        const statusTableEl = $('#picker_status_table');
        const searchInput = document.getElementById('picker_filter_search');
        const dateEl = document.getElementById('picker_filter_date');
        const applyBtn = document.getElementById('picker_filter_apply');
        const resetBtn = document.getElementById('picker_filter_reset');
        const statusEl = document.getElementById('picker_filter_status');
        const recalcBtn = document.getElementById('picker_recalculate_today');
        const auditBtn = document.getElementById('picker_audit_remaining');
        const statusModalEl = document.getElementById('modal_picker_status');
        const statusModal = statusModalEl ? new bootstrap.Modal(statusModalEl) : null;
        const statusTitleEl = document.getElementById('picker_status_title');
        const statusSearchEl = document.getElementById('picker_status_search');
        const statusExportBtn = document.getElementById('picker_status_export');
        let fpDate = null;
        let dt = null;
        let dtStatus = null;
        let statusFilter = '';

        const pickerTransitDetailModalEl = document.getElementById('modal_picker_transit_detail');
        const pickerTransitDetailModal = pickerTransitDetailModalEl ? new bootstrap.Modal(pickerTransitDetailModalEl) : null;
        const pickerTransitDetailTitleEl = document.getElementById('picker_transit_detail_title');
        const pickerTransitDetailSubtitleEl = document.getElementById('picker_transit_detail_subtitle');
        const pickerTransitDetailTotalResiEl = document.getElementById('picker_transit_detail_total_resi');
        const pickerTransitDetailTotalQtyEl = document.getElementById('picker_transit_detail_total_qty');
        const pickerTransitDetailBodyEl = document.getElementById('picker_transit_detail_body');

        const pickerTransitAuditModalEl = document.getElementById('modal_picker_transit_audit');
        const pickerTransitAuditModal = pickerTransitAuditModalEl ? new bootstrap.Modal(pickerTransitAuditModalEl) : null;
        const pickerTransitAuditSubtitleEl = document.getElementById('picker_transit_audit_subtitle');
        const pickerTransitAuditBodyEl = document.getElementById('picker_transit_audit_body');

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
            const url = params.toString() ? `${exportPickerUrl}?${params.toString()}` : exportPickerUrl;
            window.location.href = url;
        });

        statusModalEl?.addEventListener('shown.bs.modal', () => {
            dtStatus?.columns?.adjust();
        });

        // Recalculate button handler
        recalcBtn?.addEventListener('click', async () => {
            if (!pickerTransitRecalcTodayUrl) return;
            recalcBtn.disabled = true;
            const oldText = recalcBtn.textContent;
            recalcBtn.textContent = 'Memproses...';

            try {
                const response = await fetch(pickerTransitRecalcTodayUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal recalculate.');
                }

                reload();
                dtStatus?.ajax?.reload();
            } catch (e) {
                alert(e.message || 'Gagal recalculate.');
            } finally {
                recalcBtn.disabled = false;
                recalcBtn.textContent = oldText || 'Recalculate Hari Ini';
            }
        });

        // Audit button handler
        const setAuditLoading = (date) => {
            if (pickerTransitAuditSubtitleEl) pickerTransitAuditSubtitleEl.textContent = `Tanggal ${date || '-'}`;
            if (pickerTransitAuditBodyEl) {
                pickerTransitAuditBodyEl.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
        };

        const renderAuditRows = (rows) => {
            if (!pickerTransitAuditBodyEl) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                pickerTransitAuditBodyEl.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-6">Tidak ada remaining untuk tanggal ini.</td>
                    </tr>
                `;
                return;
            }

            pickerTransitAuditBodyEl.innerHTML = rows.map((r) => {
                const isExc = Number(r.is_exception || 0) > 0;
                const excBadge = isExc ? '<span class="badge badge-light-danger">Ya</span>' : '<span class="badge badge-light-success">Tidak</span>';
                return `
                    <tr>
                        <td>${r.sku || '-'}</td>
                        <td>${r.name || '-'}</td>
                        <td class="text-end">${Number(r.scanned_qty || 0)}</td>
                        <td class="text-end"><span class="badge badge-light-warning">${Number(r.remaining_qty || 0)}</span></td>
                        <td>${excBadge}</td>
                        <td style="min-width: 360px;">${r.suspect || '-'}</td>
                    </tr>
                `;
            }).join('');
        };

        auditBtn?.addEventListener('click', async () => {
            const date = dateEl?.value || todayStr || '';
            if (!pickerTransitAuditModal || !pickerTransitAuditUrl) return;

            setAuditLoading(date);
            pickerTransitAuditModal.show();

            try {
                const params = new URLSearchParams({ date });
                const response = await fetch(`${pickerTransitAuditUrl}?${params.toString()}`);
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal memuat audit.');
                }
                renderAuditRows(payload?.data || []);
            } catch (e) {
                if (pickerTransitAuditBodyEl) {
                    pickerTransitAuditBodyEl.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-danger py-6">${e.message || 'Gagal memuat audit.'}</td>
                        </tr>
                    `;
                }
            }
        });

        // Detail button handler
        const setPickerTransitDetailLoading = (date, sku) => {
            if (pickerTransitDetailTitleEl) pickerTransitDetailTitleEl.textContent = 'Detail Resi';
            if (pickerTransitDetailSubtitleEl) pickerTransitDetailSubtitleEl.textContent = `${sku || '-'} | Tanggal ${date || '-'}`;
            if (pickerTransitDetailTotalResiEl) pickerTransitDetailTotalResiEl.textContent = '0';
            if (pickerTransitDetailTotalQtyEl) pickerTransitDetailTotalQtyEl.textContent = '0';
            if (pickerTransitDetailBodyEl) {
                pickerTransitDetailBodyEl.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
        };

        const renderPickerTransitDetailRows = (rows) => {
            if (!pickerTransitDetailBodyEl) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                pickerTransitDetailBodyEl.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-6">Tidak ada resi untuk SKU ini.</td>
                    </tr>
                `;
                return;
            }
            pickerTransitDetailBodyEl.innerHTML = rows.map((r, idx) => `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${r.id_pesanan || '-'}</td>
                    <td>${r.no_resi || '-'}</td>
                    <td class="text-end">${Number(r.qty || 0)}</td>
                </tr>
            `).join('');
        };

        tableEl.on('click', '.btn-picker-transit-detail', async function() {
            const date = this.getAttribute('data-date') || '';
            const sku = this.getAttribute('data-sku') || '';
            if (!pickerTransitDetailModal || !date || !sku) return;

            setPickerTransitDetailLoading(date, sku);
            pickerTransitDetailModal.show();

            try {
                const params = new URLSearchParams({ date, sku });
                const response = await fetch(`${pickerTransitDetailUrl}?${params.toString()}`);
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal memuat detail resi.');
                }

                const meta = payload?.meta || {};
                if (pickerTransitDetailSubtitleEl) {
                    pickerTransitDetailSubtitleEl.textContent = `${meta.sku || sku} | Tanggal ${meta.date || date}`;
                }
                if (pickerTransitDetailTotalResiEl) pickerTransitDetailTotalResiEl.textContent = Number(meta.total_resi || 0).toLocaleString('id-ID');
                if (pickerTransitDetailTotalQtyEl) pickerTransitDetailTotalQtyEl.textContent = Number(meta.total_qty || 0).toLocaleString('id-ID');
                renderPickerTransitDetailRows(payload?.data || []);
            } catch (e) {
                if (pickerTransitDetailBodyEl) {
                    pickerTransitDetailBodyEl.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center text-danger py-6">${e.message || 'Gagal memuat detail resi.'}</td>
                        </tr>
                    `;
                }
            }
        });
    });
</script>
@endpush
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
                    @if((auth()->user()->email ?? '') === 'admin@gmail.com')
                        <button type="button" class="btn btn-light-primary" id="picker_recalculate_today" title="Hitung ulang remaining picker transit untuk hari ini berdasarkan packer scan hari ini.">Recalculate Hari Ini</button>
                        <button type="button" class="btn btn-light" id="picker_audit_remaining" title="Audit SKU yang masih remaining dan indikasi penyebabnya.">Audit Remaining</button>
                    @endif
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

<div class="modal fade" id="modal_picker_transit_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="picker_transit_detail_title">Detail Resi</h5>
                    <div class="text-muted fs-7" id="picker_transit_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <div class="badge badge-light-primary">Total Resi: <span id="picker_transit_detail_total_resi">0</span></div>
                    <div class="badge badge-light-success">Total Qty SKU: <span id="picker_transit_detail_total_qty">0</span></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="10%">No</th>
                                <th width="45%">ID Pesanan</th>
                                <th width="30%">No Resi</th>
                                <th width="15%" class="text-end">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="picker_transit_detail_body">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_picker_transit_audit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Audit Remaining Picker Transit</h5>
                    <div class="text-muted fs-7" id="picker_transit_audit_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>SKU</th>
                                <th>Nama</th>
                                <th class="text-end">Picked</th>
                                <th class="text-end">Remaining</th>
                                <th class="text-end">Packed (Upload Date)</th>
                                <th class="text-end">Packed (Scan Date)</th>
                                <th>Exception</th>
                                <th>Indikasi</th>
                            </tr>
                        </thead>
                        <tbody id="picker_transit_audit_body">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
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
    const pickerTransitDetailUrl = '{{ route('admin.inventory.picker-transit.detail') }}';
    const pickerTransitAuditUrl = '{{ route('admin.inventory.picker-transit.audit') }}';
    const pickerTransitRecalcTodayUrl = '{{ route('admin.inventory.picker-transit.recalculate-today') }}';
    const csrfToken = '{{ csrf_token() }}';
    const exportPickerUrl = '{{ route('admin.inventory.picker-transit.export-picker') }}';
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

        const pickerTransitDetailModalEl = document.getElementById('modal_picker_transit_detail');
        const pickerTransitDetailModal = pickerTransitDetailModalEl ? new bootstrap.Modal(pickerTransitDetailModalEl) : null;
        const pickerTransitDetailTitleEl = document.getElementById('picker_transit_detail_title');
        const pickerTransitDetailSubtitleEl = document.getElementById('picker_transit_detail_subtitle');
        const pickerTransitDetailTotalResiEl = document.getElementById('picker_transit_detail_total_resi');
        const pickerTransitDetailTotalQtyEl = document.getElementById('picker_transit_detail_total_qty');
        const pickerTransitDetailBodyEl = document.getElementById('picker_transit_detail_body');

        const pickerTransitAuditModalEl = document.getElementById('modal_picker_transit_audit');
        const pickerTransitAuditModal = pickerTransitAuditModalEl ? new bootstrap.Modal(pickerTransitAuditModalEl) : null;
        const pickerTransitAuditSubtitleEl = document.getElementById('picker_transit_audit_subtitle');
        const pickerTransitAuditBodyEl = document.getElementById('picker_transit_audit_body');

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

        pickerRecalcBtn?.addEventListener('click', async () => {
            if (!pickerTransitRecalcTodayUrl) return;
            pickerRecalcBtn.disabled = true;
            const oldText = pickerRecalcBtn.textContent;
            pickerRecalcBtn.textContent = 'Memproses...';

            try {
                const response = await fetch(pickerTransitRecalcTodayUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal recalculate.');
                }

                reloadPicker();
                dtPickerStatus?.ajax?.reload();
            } catch (e) {
                alert(e.message || 'Gagal recalculate.');
            } finally {
                pickerRecalcBtn.disabled = false;
                pickerRecalcBtn.textContent = oldText || 'Recalculate Hari Ini';
            }
        });

        const setAuditLoading = (date) => {
            if (pickerTransitAuditSubtitleEl) pickerTransitAuditSubtitleEl.textContent = `Tanggal ${date || '-'}`;
            if (pickerTransitAuditBodyEl) {
                pickerTransitAuditBodyEl.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
        };

        const renderAuditRows = (rows) => {
            if (!pickerTransitAuditBodyEl) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                pickerTransitAuditBodyEl.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-6">Tidak ada remaining untuk tanggal ini.</td>
                    </tr>
                `;
                return;
            }

            pickerTransitAuditBodyEl.innerHTML = rows.map((r) => {
                const isExc = Number(r.is_exception || 0) > 0;
                const excBadge = isExc ? '<span class="badge badge-light-danger">Ya</span>' : '<span class="badge badge-light-success">Tidak</span>';
                return `
                    <tr>
                        <td>${r.sku || '-'}</td>
                        <td>${r.name || '-'}</td>
                        <td class="text-end">${Number(r.picked_qty || 0)}</td>
                        <td class="text-end"><span class="badge badge-light-warning">${Number(r.remaining_qty || 0)}</span></td>
                        <td class="text-end">${Number(r.packed_qty_by_upload_date || 0)}</td>
                        <td class="text-end">${Number(r.packed_qty_by_scan_date || 0)}</td>
                        <td>${excBadge}</td>
                        <td style="min-width: 360px;">${r.suspect || '-'}</td>
                    </tr>
                `;
            }).join('');
        };

        pickerAuditBtn?.addEventListener('click', async () => {
            const date = pickerDateEl?.value || todayStr || '';
            if (!pickerTransitAuditModal || !pickerTransitAuditUrl) return;

            setAuditLoading(date);
            pickerTransitAuditModal.show();

            try {
                const params = new URLSearchParams({ date });
                const response = await fetch(`${pickerTransitAuditUrl}?${params.toString()}`);
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal memuat audit.');
                }
                renderAuditRows(payload?.data || []);
            } catch (e) {
                if (pickerTransitAuditBodyEl) {
                    pickerTransitAuditBodyEl.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center text-danger py-6">${e.message || 'Gagal memuat audit.'}</td>
                        </tr>
                    `;
                }
            }
        });

        const setPickerTransitDetailLoading = (date, sku) => {
            if (pickerTransitDetailTitleEl) pickerTransitDetailTitleEl.textContent = 'Detail Resi';
            if (pickerTransitDetailSubtitleEl) pickerTransitDetailSubtitleEl.textContent = `${sku || '-'} | Tanggal ${date || '-'}`;
            if (pickerTransitDetailTotalResiEl) pickerTransitDetailTotalResiEl.textContent = '0';
            if (pickerTransitDetailTotalQtyEl) pickerTransitDetailTotalQtyEl.textContent = '0';
            if (pickerTransitDetailBodyEl) {
                pickerTransitDetailBodyEl.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
        };

        const renderPickerTransitDetailRows = (rows) => {
            if (!pickerTransitDetailBodyEl) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                pickerTransitDetailBodyEl.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-6">Tidak ada resi untuk SKU ini.</td>
                    </tr>
                `;
                return;
            }
            pickerTransitDetailBodyEl.innerHTML = rows.map((r, idx) => `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${r.id_pesanan || '-'}</td>
                    <td>${r.no_resi || '-'}</td>
                    <td class="text-end">${Number(r.qty || 0)}</td>
                </tr>
            `).join('');
        };

        tableEl.on('click', '.btn-picker-transit-detail', async function() {
            const date = this.getAttribute('data-date') || '';
            const sku = this.getAttribute('data-sku') || '';
            if (!pickerTransitDetailModal || !date || !sku) return;

            setPickerTransitDetailLoading(date, sku);
            pickerTransitDetailModal.show();

            try {
                const params = new URLSearchParams({ date, sku });
                const response = await fetch(`${pickerTransitDetailUrl}?${params.toString()}`);
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal memuat detail resi.');
                }

                const meta = payload?.meta || {};
                if (pickerTransitDetailSubtitleEl) {
                    pickerTransitDetailSubtitleEl.textContent = `${meta.sku || sku} | Tanggal ${meta.date || date}`;
                }
                if (pickerTransitDetailTotalResiEl) pickerTransitDetailTotalResiEl.textContent = Number(meta.total_resi || 0).toLocaleString('id-ID');
                if (pickerTransitDetailTotalQtyEl) pickerTransitDetailTotalQtyEl.textContent = Number(meta.total_qty || 0).toLocaleString('id-ID');
                renderPickerTransitDetailRows(payload?.data || []);
            } catch (e) {
                if (pickerTransitDetailBodyEl) {
                    pickerTransitDetailBodyEl.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center text-danger py-6">${e.message || 'Gagal memuat detail resi.'}</td>
                        </tr>
                    `;
                }
            }
        });

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
>>>>>>> 529b634904102bd13e224e8bbfc0f6bdcb962e4b
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
