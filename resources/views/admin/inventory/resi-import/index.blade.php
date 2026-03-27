@extends('layouts.admin')

@section('title', 'Import Resi')
@section('page_title', 'Import Resi')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.resi-import.index', 'create');
@endphp

@section('content')
<style>
    .import-loading-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2050;
        padding: 24px;
    }
    .import-loading-card {
        background: #fff;
        border-radius: 16px;
        padding: 24px 28px;
        text-align: center;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
        min-width: 260px;
    }
</style>

<div class="import-loading-overlay" id="import_loading_overlay">
    <div class="import-loading-card">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="fw-bold mt-3">Memproses import...</div>
        <div class="text-muted fs-7 mt-1">Mohon tunggu, jangan tutup halaman.</div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="fw-bold">Import Resi</div>
        </div>
        <div class="card-toolbar">
            @if($canCreate)
                <button type="button" class="btn btn-light-primary" id="btn_import_resi" data-bs-toggle="modal" data-bs-target="#modal_import_resi">Import Excel</button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="text-muted fs-7">
            Header wajib: <strong>ID Pesanan</strong>, <strong>SKU</strong>, <strong>Jumlah</strong>, <strong>Tanggal Pembuatan</strong>.
            <strong>AWB/No. Tracking</strong> dan <strong>Kurir</strong> opsional.
        </div>
        <div class="text-muted fs-7 mt-2">
            Format tanggal akan dibaca otomatis (string atau tanggal Excel).
        </div>
    </div>
</div>

<div class="card mt-8">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" id="filter_search" placeholder="Search no resi / SKU / ID Pesanan / Kurir" value="{{ $filterSearch ?? '' }}" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date" placeholder="Tanggal" value="{{ $filterDate ?? '' }}" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="d-flex flex-wrap align-items-center gap-6 mb-4">
            <div class="fw-bold">Jumlah Pesanan: <span id="summary_orders">{{ $summaryOrders ?? 0 }}</span></div>
            <div class="fw-bold">Jumlah SKU: <span id="summary_skus">{{ $summarySkus ?? 0 }}</span></div>
        </div>
        <div class="fw-bold mb-3">Daftar Resi (Tanggal <span id="label_date">{{ $filterDate ?? $today }}</span>)</div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="resi_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>No</th>
                        <th>No Resi</th>
                        <th>Kurir</th>
                        <th>ID Pesanan</th>
                        <th>SKU</th>
                        <th>Tanggal Order</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

@if($canCreate)
    <div class="modal fade" id="modal_import_resi" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bolder">Import Resi (Excel)</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body scroll-y px-10 py-10">
                    <div class="mb-7">
                        <p class="fw-semibold mb-3">Pastikan file Excel memiliki header berikut:</p>
                        <ul class="ms-5 mb-4">
                            <li><strong>ID Pesanan</strong> (wajib)</li>
                            <li><strong>SKU</strong> (wajib)</li>
                            <li><strong>Jumlah</strong> (wajib)</li>
                            <li><strong>Tanggal Pembuatan</strong> (wajib)</li>
                            <li><strong>AWB/No. Tracking</strong> (opsional)</li>
                            <li><strong>Kurir</strong> (opsional)</li>
                        </ul>
                        <p class="text-muted small mb-0">Header akan dibaca otomatis menjadi: <code>id_pesanan, awb_no_tracking, kurir, sku, jumlah, tanggal_pembuatan</code></p>
                    </div>
                    <div class="mb-10">
                        <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                        <input type="file" class="form-control form-control-solid" id="import_resi_file" accept=".xlsx,.xls" />
                        <div class="invalid-feedback d-block" id="error_import_resi_file"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn_import_resi_submit">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    const importUrl = '{{ $importUrl ?? '' }}';
    const dataUrl = '{{ $dataUrl ?? '' }}';
    const csrfToken = '{{ csrf_token() }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const importBtn = document.getElementById('btn_import_resi');
        const importModalEl = document.getElementById('modal_import_resi');
        const importModal = importModalEl ? new bootstrap.Modal(importModalEl) : null;
        const importInput = document.getElementById('import_resi_file');
        const importError = document.getElementById('error_import_resi_file');
        const importSubmit = document.getElementById('btn_import_resi_submit');
        const loadingOverlay = document.getElementById('import_loading_overlay');
        const filterDateEl = document.getElementById('filter_date');
        const filterSearchEl = document.getElementById('filter_search');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const summaryOrdersEl = document.getElementById('summary_orders');
        const summarySkusEl = document.getElementById('summary_skus');
        const labelDateEl = document.getElementById('label_date');
        const tableEl = $('#resi_table');
        let fpDate = null;
        let dt = null;

        if (typeof flatpickr !== 'undefined' && filterDateEl) {
            fpDate = flatpickr(filterDateEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        if (tableEl.length && $.fn.DataTable) {
            dt = tableEl.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                ordering: false,
                ajax: {
                    url: dataUrl,
                    dataSrc: 'data',
                    data: function(params) {
                        params.q = filterSearchEl?.value || '';
                        params.date = filterDateEl?.value || '';
                    }
                },
                columns: [
                    { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }},
                    { data: 'no_resi' },
                    { data: 'kurir' },
                    { data: 'id_pesanan' },
                    { data: 'sku' },
                    { data: 'tanggal_pesanan' },
                ],
                language: {
                    emptyTable: 'Belum ada data',
                    processing: 'Memuat...',
                },
            });

            tableEl.on('xhr.dt', function () {
                const json = dt?.ajax?.json?.();
                if (json?.summary) {
                    if (summaryOrdersEl) summaryOrdersEl.textContent = json.summary.orders ?? '0';
                    if (summarySkusEl) summarySkusEl.textContent = json.summary.skus ?? '0';
                }
            });
        }

        const reloadTable = () => {
            if (labelDateEl) labelDateEl.textContent = filterDateEl?.value || todayStr || '';
            dt?.ajax?.reload();
        };

        filterApplyBtn?.addEventListener('click', reloadTable);
        filterSearchEl?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadTable();
        });
        filterResetBtn?.addEventListener('click', () => {
            if (fpDate && todayStr) {
                fpDate.setDate(todayStr, true);
            } else if (filterDateEl && todayStr) {
                filterDateEl.value = todayStr;
            }
            if (filterSearchEl) filterSearchEl.value = '';
            reloadTable();
        });

        importBtn?.addEventListener('click', () => {
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
        });

        const setLoading = (state) => {
            if (!loadingOverlay) return;
            loadingOverlay.style.display = state ? 'flex' : 'none';
            if (importSubmit) importSubmit.disabled = state;
            if (importInput) importInput.disabled = state;
            if (importBtn) importBtn.disabled = state;
            document.body.style.cursor = state ? 'progress' : '';
        };

        importSubmit?.addEventListener('click', async () => {
            if (!importUrl) return;
            if (importError) importError.textContent = '';
            const file = importInput?.files?.[0];
            if (!file) {
                if (importError) importError.textContent = 'Pilih file Excel terlebih dahulu.';
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            try {
                setLoading(true);
                const res = await fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const isJson = res.headers.get('content-type')?.includes('application/json');
                const json = isJson ? await res.json() : {};

                if (!res.ok) {
                    const msg = json?.errors?.file?.[0] || json?.message || 'Gagal import';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg, 'error');
                    } else if (importError) {
                        importError.textContent = msg;
                    }
                    return;
                }

                const successMsg = json?.message || 'Import resi berhasil';
                if (typeof Swal !== 'undefined') {
                    const count = json?.details ? ` (detail: ${json.details})` : '';
                    Swal.fire('Berhasil', successMsg + count, 'success');
                }

                if (importInput) importInput.value = '';
                importModal?.hide();
                reloadTable();
            } catch (e) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Gagal import', 'error');
                } else if (importError) {
                    importError.textContent = 'Gagal import';
                }
            } finally {
                setLoading(false);
            }
        });
    });
</script>
@endpush
