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
                <button type="button" class="btn btn-light-warning" id="btn_recalculate_picking">Recalculate</button>
                <button type="button" class="btn btn-light-primary" id="btn_export_picking_list">Export Excel</button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_add_picking_qty">Tambah Qty</button>
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
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_add_picking_qty" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Tambah Qty Picking List</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body mx-5 mx-xl-15 my-7">
                <form id="form_add_picking_qty">
                    @csrf
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Tanggal Picking</label>
                        <input type="text" class="form-control form-control-solid" id="add_qty_date" name="list_date" placeholder="YYYY-MM-DD" value="{{ $today ?? '' }}" />
                        <div class="text-danger fs-7 mt-2" data-error="list_date"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Jenis Penyesuaian</label>
                        <select class="form-select form-select-solid" id="add_qty_mode" name="mode">
                            <option value="add">Tambah Qty</option>
                            <option value="reduce">Kurangi Qty</option>
                        </select>
                        <div class="text-danger fs-7 mt-2" data-error="mode"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">SKU</label>
                        <input type="text" class="form-control form-control-solid" id="add_qty_sku" name="sku" placeholder="Masukkan SKU" />
                        <div class="text-danger fs-7 mt-2" data-error="sku"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Qty</label>
                        <input type="number" min="1" class="form-control form-control-solid" id="add_qty_qty" name="qty" placeholder="Qty" />
                        <div class="text-danger fs-7 mt-2" data-error="qty"></div>
                    </div>
                    <div class="text-muted fs-7 mb-7">
                        Pilih tambah untuk menambahkan qty baru atau kurangi untuk mengurangi qty & remaining qty pada SKU dan tanggal yang sama. Jika belum ada, sistem akan membuat baris baru saat menambah.
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btn_submit_add_qty">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_return_exception" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Kembalikan Stok</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body mx-5 mx-xl-15 my-7">
                <form id="form_return_exception">
                    @csrf
                    <div class="fv-row mb-5">
                        <label class="fs-6 fw-bold form-label mb-2">Tanggal</label>
                        <input type="text" class="form-control form-control-solid" id="return_exception_date" name="list_date" readonly />
                    </div>
                    <div class="fv-row mb-5">
                        <label class="fs-6 fw-bold form-label mb-2">SKU</label>
                        <input type="text" class="form-control form-control-solid" id="return_exception_sku" name="sku" readonly />
                    </div>
                    <div class="fv-row mb-5">
                        <label class="fs-6 fw-bold form-label mb-2">Nama</label>
                        <input type="text" class="form-control form-control-solid" id="return_exception_name" readonly />
                    </div>
                    <div class="fv-row mb-5">
                        <label class="fs-6 fw-bold form-label mb-2">Qty Exception</label>
                        <input type="text" class="form-control form-control-solid" id="return_exception_qty" readonly />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Qty Return</label>
                        <input type="number" min="1" class="form-control form-control-solid" id="return_qty" name="qty" placeholder="Qty" />
                        <div class="text-danger fs-7 mt-2" data-error="qty"></div>
                    </div>
                    <div class="text-muted fs-7 mb-7">
                        Qty return tidak boleh melebihi jumlah exception.
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btn_submit_return">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const dataUrlExceptions = '{{ $dataUrlExceptions }}';
    const exportUrl = '{{ route('admin.inventory.picking-list.export') }}';
    const recalcUrl = '{{ route('admin.inventory.picking-list.recalculate') }}';
    const addQtyUrl = '{{ route('admin.inventory.picking-list.store-qty') }}';
    const returnExceptionUrl = '{{ route('admin.inventory.picking-list.exception-return') }}';
    const todayStr = '{{ $today ?? '' }}';
    const csrfToken = '{{ csrf_token() }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picking_list_table');
        const exceptionTableEl = $('#picking_exception_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateEl = document.getElementById('filter_date');
        const statusEl = document.getElementById('filter_status');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const recalcBtn = document.getElementById('btn_recalculate_picking');
        const exportBtn = document.getElementById('btn_export_picking_list');
        const isSameDay = (value, compare) => {
            const a = (value || '').trim();
            const b = (compare || '').trim();
            return a !== '' && b !== '' && a === b;
        };
        const updateRecalcState = () => {
            if (!recalcBtn) return;
            const selected = (dateEl?.value || '').trim();
            recalcBtn.disabled = !isSameDay(selected, todayStr);
        };
        const addQtyModalEl = document.getElementById('modal_add_picking_qty');
        const returnModalEl = document.getElementById('modal_return_exception');
        const returnForm = document.getElementById('form_return_exception');
        const returnModal = (typeof bootstrap !== 'undefined' && returnModalEl) ? new bootstrap.Modal(returnModalEl) : null;
        const returnDateInput = document.getElementById('return_exception_date');
        const returnSkuInput = document.getElementById('return_exception_sku');
        const returnNameInput = document.getElementById('return_exception_name');
        const returnExceptionQtyInput = document.getElementById('return_exception_qty');
        const returnQtyInput = document.getElementById('return_qty');
        const addQtyForm = document.getElementById('form_add_picking_qty');
        const addQtyDateInput = document.getElementById('add_qty_date');
        const addQtySkuInput = document.getElementById('add_qty_sku');
        const addQtyModal = (typeof bootstrap !== 'undefined' && addQtyModalEl) ? new bootstrap.Modal(addQtyModalEl) : null;
        const summaryOngoingEl = document.getElementById('summary_ongoing');
        const summaryDoneEl = document.getElementById('summary_done');
        let fpDate = null;
        let addQtyDatePicker = null;
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
            if (addQtyDateInput) {
                addQtyDatePicker = flatpickr(addQtyDateInput, { dateFormat: 'Y-m-d', allowInput: true });
            }
        } else if (addQtyDateInput && todayStr) {
            addQtyDateInput.value = todayStr;
        }
        updateRecalcState();

        const resetAddQtyDate = () => {
            if (addQtyDatePicker) {
                if (todayStr) {
                    addQtyDatePicker.setDate(todayStr, true);
                } else {
                    addQtyDatePicker.clear();
                }
            } else if (addQtyDateInput) {
                addQtyDateInput.value = todayStr || '';
            }
        };
        resetAddQtyDate();

        const clearAddQtyErrors = () => {
            addQtyForm?.querySelectorAll('[data-error]').forEach((el) => { el.textContent = ''; });
        };

        const resetAddQtyForm = () => {
            addQtyForm?.reset();
            resetAddQtyDate();
        };

        addQtyModalEl?.addEventListener('shown.bs.modal', () => {
            setTimeout(() => addQtySkuInput?.focus(), 200);
        });

        addQtyModalEl?.addEventListener('hidden.bs.modal', () => {
            clearAddQtyErrors();
            resetAddQtyForm();
        });

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
                { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                    return `<button type="button" class="btn btn-sm btn-light-primary btn-return" data-date="${row.list_date || row.date}" data-sku="${row.sku}" data-name="${row.name}" data-qty="${row.qty}">Return</button>`;
                }},
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
        dateEl?.addEventListener('change', updateRecalcState);
        statusEl?.addEventListener('change', reloadAll);
        filterResetBtn?.addEventListener('click', () => {
            if (fpDate && todayStr) {
                fpDate.setDate(todayStr, true);
            } else if (dateEl) {
                dateEl.value = todayStr || '';
            }
            if (searchInput) searchInput.value = '';
            if (statusEl) statusEl.value = '';
            updateRecalcState();
            reloadAll();
        });

        exportBtn?.addEventListener('click', () => {
            const params = new URLSearchParams();
            const q = (searchInput?.value || '').trim();
            if (q) params.set('q', q);
            if (dateEl?.value) params.set('date', dateEl.value);
            if (statusEl?.value) params.set('status', statusEl.value);
            const url = params.toString() ? `${exportUrl}?${params.toString()}` : exportUrl;
            window.location.href = url;
        });

        recalcBtn?.addEventListener('click', async () => {
            const listDate = (dateEl?.value || todayStr || '').trim();
            if (!listDate) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Tanggal picking belum dipilih.', 'error');
                }
                return;
            }
            if (!isSameDay(listDate, todayStr)) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Tidak diizinkan', 'Recalculate hanya bisa dilakukan untuk tanggal hari ini.', 'warning');
                }
                return;
            }

            const runRecalc = async () => {
                try {
                    recalcBtn.disabled = true;
                    const formData = new FormData();
                    formData.append('list_date', listDate);
                    const res = await fetch(recalcUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });
                    const text = await res.text();
                    let json = null;
                    try { json = JSON.parse(text); } catch (err) { /* ignore */ }
                    if (!res.ok) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', json?.message || 'Gagal melakukan rekalkulasi', 'error');
                        }
                        return;
                    }
                    if (typeof Swal !== 'undefined') {
                        const summary = json?.summary;
                        const info = summary
                            ? `Updated: ${summary.updated ?? 0}, Deleted: ${summary.deleted ?? 0}, Exceptions: ${summary.exceptions ?? 0}`
                            : '';
                        Swal.fire('Berhasil', `${json?.message || 'Rekalkulasi selesai.'} ${info}`.trim(), 'success');
                    }
                    reloadAll();
                } catch (err) {
                    console.error(err);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', 'Gagal melakukan rekalkulasi', 'error');
                    }
                } finally {
                    if (recalcBtn) recalcBtn.disabled = false;
                }
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Recalculate Picking List?',
                    text: `Tanggal ${listDate} akan dihitung ulang berdasarkan resi dan transit.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, recalculation',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (result.isConfirmed) {
                        runRecalc();
                    }
                });
            } else {
                runRecalc();
            }
        });

        addQtyForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearAddQtyErrors();
            const formData = new FormData(addQtyForm);
            try {
                const res = await fetch(addQtyUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); } catch (err) { /* ignore */ }
                if (!res.ok) {
                    if (res.status === 422 && json?.errors) {
                        Object.entries(json.errors).forEach(([field, messages]) => {
                            const errEl = addQtyForm.querySelector(`[data-error="${field}"]`);
                            if (errEl) errEl.textContent = messages.join(', ');
                        });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json?.message || 'Gagal menyimpan data', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json?.message || 'Qty berhasil ditambahkan', 'success');
                }
                addQtyModal?.hide();
                reloadAll();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Gagal menyimpan data', 'error');
                }
            }
        });

        const clearReturnErrors = () => {
            returnForm?.querySelectorAll('[data-error]').forEach((el) => { el.textContent = ''; });
        };

        const openReturnModal = (row) => {
            if (returnDateInput) returnDateInput.value = row.date || '';
            if (returnSkuInput) returnSkuInput.value = row.sku || '';
            if (returnNameInput) returnNameInput.value = row.name || '';
            if (returnExceptionQtyInput) returnExceptionQtyInput.value = row.qty ?? 0;
            if (returnQtyInput) returnQtyInput.value = '';
            clearReturnErrors();
            returnModal?.show();
        };

        exceptionTableEl.on('click', '.btn-return', function (e) {
            e.preventDefault();
            const row = {
                date: this.getAttribute('data-date'),
                sku: this.getAttribute('data-sku'),
                name: this.getAttribute('data-name'),
                qty: this.getAttribute('data-qty'),
            };
            openReturnModal(row);
        });

        returnForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearReturnErrors();
            const formData = new FormData(returnForm);
            try {
                const res = await fetch(returnExceptionUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); } catch (err) { /* ignore */ }
                if (!res.ok) {
                    if (res.status === 422 && json?.errors) {
                        Object.entries(json.errors).forEach(([field, messages]) => {
                            const errEl = returnForm.querySelector(`[data-error="${field}"]`);
                            if (errEl) errEl.textContent = messages.join(', ');
                        });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json?.message || 'Gagal mengembalikan stok', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json?.message || 'Stok berhasil dikembalikan', 'success');
                }
                returnModal?.hide();
                reloadAll();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Gagal mengembalikan stok', 'error');
                }
            }
        });
    });
</script>
@endpush
