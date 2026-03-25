@extends('layouts.admin')

@section('title', 'History Picker')
@section('page_title', 'History Picker')

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
            <div class="d-flex align-items-center gap-2 me-4">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $today ?? '' }}" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today ?? '' }}" />
                <button type="button" class="btn btn-light" id="filter_date_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_date_reset">Reset</button>
            </div>
            <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                    <span class="svg-icon svg-icon-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z" fill="black" />
                        </svg>
                    </span>
                    Filter
                </button>
                <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true">
                    <div class="px-7 py-5">
                        <div class="fs-5 text-dark fw-bolder">Filter Options</div>
                    </div>
                    <div class="separator border-gray-200"></div>
                    <div class="px-7 py-5">
                        <div class="mb-10">
                            <label class="form-label fs-6 fw-bold">User:</label>
                            <select id="filter_picker_user" class="form-select form-select-solid fw-bolder" data-control="select2" data-placeholder="Select option" data-allow-clear="true">
                                <option value="">Semua</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-10">
                            <label class="form-label fs-6 fw-bold">Status:</label>
                            <select id="filter_picker_status" class="form-select form-select-solid fw-bolder" data-control="select2" data-placeholder="Select option" data-allow-clear="true">
                                <option value="">Semua</option>
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light btn-active-light-primary me-2" id="filter_picker_reset">Reset</button>
                            <button type="button" class="btn btn-primary" id="filter_picker_apply">Apply</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="picker_sessions_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Picker</th>
                        <th>Status</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
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
    const submitUrlTpl = '{{ route('admin.outbound.picker-sessions.submit', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.outbound.picker-sessions.destroy', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picker_sessions_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const applyBtn = document.getElementById('filter_picker_apply');
        const resetBtn = document.getElementById('filter_picker_reset');
        const userSelect = document.getElementById('filter_picker_user');
        const statusSelect = document.getElementById('filter_picker_status');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const dateApplyBtn = document.getElementById('filter_date_apply');
        const dateResetBtn = document.getElementById('filter_date_reset');
        let fpFrom = null;
        let fpTo = null;

        const select2Safe = (el, placeholder) => {
            if (el && typeof $ !== 'undefined' && $.fn.select2) {
                $(el).select2({ placeholder, allowClear: true, width: '100%' })
                    .on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
            }
        };

        select2Safe(userSelect, 'Semua');
        select2Safe(statusSelect, 'Semua');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
                if (todayStr && !dateFromEl.value) fpFrom.setDate(todayStr, true);
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
                if (todayStr && !dateToEl.value) fpTo.setDate(todayStr, true);
            }
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.user_id = userSelect?.value || '';
                    params.status = statusSelect?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'picker' },
                { data: 'status', render: (data) => {
                    if (data === 'submitted') {
                        return '<span class="badge badge-light-success">Submitted</span>';
                    }
                    return '<span class="badge badge-light-warning">Draft</span>';
                }},
                { data: 'started_at' },
                { data: 'submitted_at' },
                { data: 'item' },
                { data: 'qty' },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row) => {
                    if (row?.status !== 'draft') {
                        return '-';
                    }
                    const actions = [];
                    actions.push(`<a href="#" class="btn btn-sm btn-light btn-active-light-primary btn-submit" data-id="${data}">Submit</a>`);
                    if (Number(row?.qty || 0) === 0) {
                        actions.push(`<a href="#" class="btn btn-sm btn-light-danger btn-delete" data-id="${data}">Hapus</a>`);
                    }
                    return `<div class="text-end d-flex justify-content-end gap-2">${actions.join('')}</div>`;
                }},
            ]
        });

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        applyBtn?.addEventListener('click', reloadTable);
        resetBtn?.addEventListener('click', () => {
            if (userSelect) userSelect.value = '';
            if (statusSelect) statusSelect.value = '';
            if (typeof $ !== 'undefined' && $(userSelect).data('select2')) {
                $(userSelect).val('').trigger('change.select2');
            }
            if (typeof $ !== 'undefined' && $(statusSelect).data('select2')) {
                $(statusSelect).val('').trigger('change.select2');
            }
            reloadTable();
        });
        dateApplyBtn?.addEventListener('click', reloadTable);
        dateResetBtn?.addEventListener('click', () => {
            if (fpFrom && todayStr) {
                fpFrom.setDate(todayStr, true);
            } else if (dateFromEl) {
                dateFromEl.value = todayStr || '';
            }
            if (fpTo && todayStr) {
                fpTo.setDate(todayStr, true);
            } else if (dateToEl) {
                dateToEl.value = todayStr || '';
            }
            reloadTable();
        });

        const showInsufficientStock = (details) => {
            if (!Array.isArray(details) || !details.length) return;
            if (typeof Swal === 'undefined') return;
            const list = details.map((row) => {
                const sku = row.sku || '-';
                const name = row.name ? ` • ${row.name}` : '';
                const available = typeof row.available !== 'undefined' ? row.available : '-';
                const required = typeof row.required !== 'undefined' ? row.required : '-';
                return `<li style="margin-bottom:6px;"><strong>${sku}</strong>${name}<br><span style="color:#64748b;">Tersedia ${available}, butuh ${required}</span></li>`;
            }).join('');
            Swal.fire({
                icon: 'error',
                title: 'Stok tidak mencukupi',
                html: `<div style="text-align:left; font-size:14px;">Item berikut stoknya kurang:</div><ul style="text-align:left; padding-left:18px; margin-top:8px;">${list}</ul>`,
            });
        };

        tableEl.on('click', '.btn-submit', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            let confirmed = true;
            if (typeof AppSwal !== 'undefined' && AppSwal.confirm) {
                confirmed = await AppSwal.confirm('Submit batch picker ini?', {
                    confirmButtonText: 'Submit',
                });
            } else if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Submit batch?',
                    text: 'Batch akan dikunci dan stok akan berkurang.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Submit',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            } else {
                confirmed = window.confirm('Submit batch picker ini?');
            }
            if (!confirmed) return;
            try {
                const res = await fetch(submitUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); } catch (err) { json = null; }
                if (!res.ok) {
                    if (json?.insufficient) {
                        showInsufficientStock(json.insufficient);
                        return;
                    }
                    const msg = json?.message || 'Gagal submit sesi';
                    if (typeof AppSwal !== 'undefined' && AppSwal.error) {
                        AppSwal.error(msg);
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg, 'error');
                    } else {
                        alert(msg);
                    }
                    return;
                }
                if (typeof AppSwal !== 'undefined' && AppSwal.success) {
                    AppSwal.success(json?.message || 'Sesi berhasil disubmit');
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json?.message || 'Sesi berhasil disubmit', 'success');
                }
                dt.ajax.reload(null, false);
            } catch (err) {
                if (typeof AppSwal !== 'undefined' && AppSwal.error) {
                    AppSwal.error('Gagal submit sesi');
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Gagal submit sesi', 'error');
                }
            }
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            let confirmed = true;
            if (typeof AppSwal !== 'undefined' && AppSwal.confirm) {
                confirmed = await AppSwal.confirm('Hapus batch kosong ini?', {
                    confirmButtonText: 'Hapus',
                });
            } else if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Hapus batch?',
                    text: 'Batch kosong akan dihapus.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            } else {
                confirmed = window.confirm('Hapus batch kosong ini?');
            }
            if (!confirmed) return;
            try {
                const res = await fetch(deleteUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); } catch (err) { json = null; }
                if (!res.ok) {
                    const msg = json?.message || 'Gagal menghapus sesi';
                    if (typeof AppSwal !== 'undefined' && AppSwal.error) {
                        AppSwal.error(msg);
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg, 'error');
                    } else {
                        alert(msg);
                    }
                    return;
                }
                if (typeof AppSwal !== 'undefined' && AppSwal.success) {
                    AppSwal.success(json?.message || 'Sesi berhasil dihapus');
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json?.message || 'Sesi berhasil dihapus', 'success');
                }
                dt.ajax.reload(null, false);
            } catch (err) {
                if (typeof AppSwal !== 'undefined' && AppSwal.error) {
                    AppSwal.error('Gagal menghapus sesi');
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Gagal menghapus sesi', 'error');
                }
            }
        });
    });
</script>
@endpush
