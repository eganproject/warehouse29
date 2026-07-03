@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@php
    $initialItems = $transaction
        ? $transaction->items->map(function ($row) {
            return [
                'item_id' => $row->item_id,
                'sku' => $row->item?->sku,
                'name' => $row->item?->name,
                'qty_resi' => (int) ($row->qty_resi ?? $row->qty_received ?? $row->qty ?? 0),
                'qty_received' => (int) ($row->qty_received ?? $row->qty ?? 0),
                'qty_difference' => (int) ($row->qty_difference ?? 0),
                'qty_good' => (int) ($row->qty_good ?? 0),
                'qty_damaged' => (int) ($row->qty_damaged ?? 0),
                'return_reason_id' => $row->return_reason_id,
                'return_reason_note' => $row->return_reason_note,
                'note' => $row->note,
            ];
        })->values()
        : collect();
    $initialResi = $transaction?->resi ? [
        'id' => $transaction->resi->id,
        'id_pesanan' => $transaction->resi->id_pesanan,
        'no_resi' => $transaction->resi->no_resi,
        'kurir' => $transaction->resi->kurir?->name,
        'tanggal_upload' => $transaction->resi->tanggal_upload?->format('Y-m-d'),
    ] : null;
@endphp

@push('styles')
<style>
    .return-items-list {
        display: grid;
        gap: 1rem;
    }

    .return-item-card {
        border: 1px solid #e4e6ef;
        border-radius: 8px;
        background: #fff;
        padding: 1.25rem;
    }

    .return-item-card.is-difference {
        border-color: #f6c000;
        background: #fffdf4;
    }

    .return-item-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .return-item-index {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: #334155;
        font-weight: 700;
        flex: 0 0 34px;
    }

    .return-item-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1.45fr) repeat(5, minmax(92px, .5fr));
        gap: 1rem;
        align-items: start;
    }

    .return-item-reason-grid {
        display: grid;
        grid-template-columns: minmax(220px, .8fr) minmax(220px, 1fr) minmax(220px, 1fr);
        gap: 1rem;
        margin-top: 1rem;
    }

    .return-qty-field .form-label {
        margin-bottom: .35rem;
        white-space: nowrap;
    }

    .return-diff-input {
        font-weight: 700;
        color: #92400e;
        background-color: #fff8dd !important;
    }

    .return-damaged-input {
        font-weight: 700;
        color: #991b1b;
    }

    .return-item-summary {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .75rem;
    }

    .return-item-summary .badge {
        font-size: .75rem;
        border-radius: 6px;
    }

    @media (max-width: 1199.98px) {
        .return-item-grid {
            grid-template-columns: minmax(240px, 1fr) repeat(3, minmax(96px, .45fr));
        }

        .return-item-reason-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .return-item-card {
            padding: 1rem;
        }

        .return-item-head {
            align-items: center;
        }

        .return-item-grid {
            grid-template-columns: 1fr 1fr;
        }

        .return-item-grid .return-item-field-main {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 479.98px) {
        .return-item-grid {
            grid-template-columns: 1fr;
        }

        .return-item-head {
            gap: .75rem;
        }
    }
</style>
@endpush

@section('content')
<form id="return_form" class="form" autocomplete="off">
    @csrf
    @if($mode === 'edit')
        <input type="hidden" name="_method" value="PUT">
    @endif
    <input type="hidden" name="resi_id" id="resi_id" value="{{ $transaction->resi_id ?? '' }}">

    <div class="card mb-6">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div>
                    <h2 class="fw-bolder mb-1">{{ $pageTitle }}</h2>
                    <div class="text-muted fs-7">Scan no resi atau ID pesanan untuk memuat item otomatis.</div>
                </div>
            </div>
            <div class="card-toolbar d-flex gap-2">
                <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
                <button type="submit" class="btn btn-primary" id="btn_save_return">
                    <span class="indicator-label">Simpan</span>
                    <span class="indicator-progress">Menyimpan...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
        </div>
        <div class="card-body py-6">
            <div class="row g-5">
                <div class="col-lg-5">
                    <label class="fs-6 fw-bold form-label mb-2">Scan / Input Resi</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-solid" id="scan_resi"
                            placeholder="Scan no resi atau ID pesanan"
                            value="{{ $transaction->return_resi_no ?? '' }}">
                        <button class="btn btn-light-primary" type="button" id="btn_lookup_resi">Cari</button>
                    </div>
                    <input type="hidden" name="return_resi_no" id="return_resi_no" value="{{ $transaction->return_resi_no ?? '' }}">
                    <div class="invalid-feedback d-block" id="error_return_resi_no"></div>
                </div>
                <div class="col-lg-3">
                    <label class="required fs-6 fw-bold form-label mb-2">Tanggal</label>
                    <input type="text" class="form-control form-control-solid" name="transacted_at" id="transacted_at"
                        value="{{ $transaction?->transacted_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }}"
                        placeholder="YYYY-MM-DD HH:mm">
                    <div class="invalid-feedback d-block" id="error_transacted_at"></div>
                </div>
                <div class="col-lg-4">
                    <label class="fs-6 fw-bold form-label mb-2">Ref No</label>
                    <input type="text" class="form-control form-control-solid" name="ref_no" id="ref_no" value="{{ $transaction->ref_no ?? '' }}">
                    <div class="invalid-feedback d-block" id="error_ref_no"></div>
                </div>
            </div>

            <div class="mt-5 d-none" id="resi_panel">
                <div class="alert alert-primary d-flex align-items-start p-5 mb-0">
                    <i class="fas fa-barcode fs-2 me-4"></i>
                    <div>
                        <div class="fw-bold" id="resi_title">-</div>
                        <div class="text-muted fs-7" id="resi_meta">-</div>
                    </div>
                </div>
            </div>

            <div class="fv-row mt-5">
                <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                <textarea class="form-control form-control-solid" name="note" id="note" rows="3">{{ $transaction->note ?? '' }}</textarea>
                <div class="invalid-feedback d-block" id="error_note"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <h3 class="fw-bolder mb-0">Item Retur</h3>
            </div>
            <div class="card-toolbar">
                <button type="button" class="btn btn-light" id="btn_add_item">Tambah Item Manual</button>
            </div>
        </div>
        <div class="card-body py-6">
            <div class="alert alert-light-primary d-flex align-items-start p-5 mb-5">
                <i class="fas fa-circle-info fs-2 me-4"></i>
                <div>
                    <div class="fw-bold">Qty Resi adalah jumlah pada data pesanan. Qty diterima adalah fisik yang kembali.</div>
                    <div class="text-muted fs-7">Selisih dihitung otomatis. Qty bagus + qty rusak harus sama dengan qty diterima.</div>
                </div>
            </div>
            <div class="return-items-list" id="items_body"></div>
            <div class="invalid-feedback d-block" id="error_items"></div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const lookupUrl = @json($lookupUrl);
    const submitUrl = @json($mode === 'edit' ? $updateUrl : $storeUrl);
    const backUrl = @json($backUrl);
    const csrfToken = @json(csrf_token());
    const itemOptions = @json($items->map(fn ($item) => ['id' => $item->id, 'label' => $item->sku.' - '.$item->name])->values());
    const reasonOptions = @json($returnReasons->map(fn ($reason) => ['id' => $reason->id, 'name' => $reason->name])->values());
    const initialItems = @json($initialItems);
    const initialResi = @json($initialResi);

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('return_form');
        const body = document.getElementById('items_body');
        const addBtn = document.getElementById('btn_add_item');
        const lookupBtn = document.getElementById('btn_lookup_resi');
        const scanInput = document.getElementById('scan_resi');
        const resiIdInput = document.getElementById('resi_id');
        const resiNoInput = document.getElementById('return_resi_no');
        const resiPanel = document.getElementById('resi_panel');
        const resiTitle = document.getElementById('resi_title');
        const resiMeta = document.getElementById('resi_meta');
        const saveBtn = document.getElementById('btn_save_return');

        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[m]));

        const numberValue = (value) => {
            const parsed = parseInt(value, 10);
            return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
        };

        const itemSelectHtml = (selected) => {
            const options = ['<option value="">Pilih item</option>']
                .concat(itemOptions.map((item) => `<option value="${item.id}" ${String(selected || '') === String(item.id) ? 'selected' : ''}>${esc(item.label)}</option>`));
            return options.join('');
        };

        const reasonSelectHtml = (selected) => {
            const options = ['<option value="">Pilih penyebab</option>']
                .concat(reasonOptions.map((reason) => `<option value="${reason.id}" ${String(selected || '') === String(reason.id) ? 'selected' : ''}>${esc(reason.name)}</option>`));
            return options.join('');
        };

        const refreshIndexes = () => {
            Array.from(body.querySelectorAll('tr')).forEach((row, index) => {
                row.dataset.index = index;
                row.querySelectorAll('[data-name]').forEach((input) => {
                    input.name = `items[${index}][${input.dataset.name}]`;
                });
                row.querySelectorAll('[data-error-for]').forEach((el) => {
                    el.id = `error_items_${index}_${el.dataset.errorFor}`;
                });
            });
        };

        const initSelect = (select) => {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(select).select2({ width: '100%', placeholder: select.dataset.placeholder || 'Pilih' });
            }
        };

        const syncRow = (row, changed) => {
            const qtyResi = numberValue(row.querySelector('[data-name="qty_resi"]')?.value);
            const receivedEl = row.querySelector('[data-name="qty_received"]');
            const goodEl = row.querySelector('[data-name="qty_good"]');
            const damagedEl = row.querySelector('[data-name="qty_damaged"]');
            const diffEl = row.querySelector('[data-name="qty_difference"]');
            let received = numberValue(receivedEl?.value);
            if (qtyResi > 0 && received > qtyResi) {
                received = qtyResi;
                receivedEl.value = received;
            }

            if (changed === 'qty_damaged') {
                let damaged = numberValue(damagedEl?.value);
                if (damaged > received) damaged = received;
                damagedEl.value = damaged;
                goodEl.value = Math.max(received - damaged, 0);
            } else {
                let good = numberValue(goodEl?.value);
                if (good > received) good = received;
                goodEl.value = good;
                damagedEl.value = Math.max(received - good, 0);
            }

            diffEl.value = Math.max(qtyResi - received, 0);
        };

        const addRow = (data = {}) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <select class="form-select form-select-solid item-select" data-name="item_id" data-placeholder="Pilih item" required>
                        ${itemSelectHtml(data.item_id)}
                    </select>
                    ${data.item_found === false ? '<div class="text-danger fs-8 mt-1">SKU resi belum ada di master item.</div>' : ''}
                    <div class="invalid-feedback d-block" data-error-for="item_id"></div>
                </td>
                <td>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty_resi" value="${esc(data.qty_resi || 1)}" required>
                    <div class="invalid-feedback d-block" data-error-for="qty_resi"></div>
                </td>
                <td>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty_received" value="${esc(data.qty_received ?? data.qty_resi ?? 1)}" required>
                    <div class="invalid-feedback d-block" data-error-for="qty_received"></div>
                </td>
                <td>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="qty_difference" value="${esc(data.qty_difference || 0)}" readonly>
                </td>
                <td>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="qty_good" value="${esc(data.qty_good ?? data.qty_received ?? data.qty_resi ?? 1)}" required>
                    <div class="invalid-feedback d-block" data-error-for="qty_good"></div>
                </td>
                <td>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="qty_damaged" value="${esc(data.qty_damaged || 0)}" required>
                    <div class="invalid-feedback d-block" data-error-for="qty_damaged"></div>
                </td>
                <td>
                    <select class="form-select form-select-solid reason-select" data-name="return_reason_id" data-placeholder="Pilih penyebab">
                        ${reasonSelectHtml(data.return_reason_id)}
                    </select>
                    <div class="invalid-feedback d-block" data-error-for="return_reason_id"></div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-solid" data-name="return_reason_note" value="${esc(data.return_reason_note || '')}">
                    <div class="invalid-feedback d-block" data-error-for="return_reason_note"></div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-solid" data-name="note" value="${esc(data.note || '')}">
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-icon btn-light-danger btn-remove-row">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            body.appendChild(row);
            refreshIndexes();
            initSelect(row.querySelector('.item-select'));
            initSelect(row.querySelector('.reason-select'));
            syncRow(row);
        };

        const clearRows = () => {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(body).find('select.select2-hidden-accessible').select2('destroy');
            }
            body.innerHTML = '';
        };

        const showResi = (resi) => {
            if (!resi) {
                resiPanel.classList.add('d-none');
                return;
            }
            resiPanel.classList.remove('d-none');
            resiTitle.textContent = `${resi.no_resi || '-'} / ${resi.id_pesanan || '-'}`;
            resiMeta.textContent = [
                resi.kurir ? `Kurir: ${resi.kurir}` : null,
                resi.tanggal_upload ? `Upload: ${resi.tanggal_upload}` : null,
            ].filter(Boolean).join(' | ') || '-';
        };

        const lookupResi = async () => {
            const code = scanInput.value.trim();
            if (!code) return;
            lookupBtn.disabled = true;
            lookupBtn.textContent = 'Mencari...';
            try {
                const res = await fetch(`${lookupUrl}?code=${encodeURIComponent(code)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const json = await res.json();
                resiNoInput.value = code;
                if (!json.found) {
                    resiIdInput.value = '';
                    showResi(null);
                    clearRows();
                    addRow({ qty_resi: 1, qty_received: 1, qty_good: 1 });
                    if (window.Swal) Swal.fire('Resi tidak ditemukan', json.message || 'Input item manual.', 'info');
                    return;
                }

                resiIdInput.value = json.resi.id;
                resiNoInput.value = json.resi.no_resi || json.resi.id_pesanan || code;
                showResi(json.resi);
                clearRows();
                (json.items || []).forEach((item) => {
                    addRow({
                        item_id: item.item_id,
                        qty_resi: item.qty_resi,
                        qty_received: item.qty_resi,
                        qty_good: item.qty_resi,
                        qty_damaged: 0,
                        item_found: item.item_found,
                    });
                });
                if (!json.items || json.items.length === 0) addRow();
            } catch (err) {
                if (window.Swal) Swal.fire('Error', 'Gagal mencari resi', 'error');
            } finally {
                lookupBtn.disabled = false;
                lookupBtn.textContent = 'Cari';
            }
        };

        const clearErrors = () => {
            document.querySelectorAll('.invalid-feedback').forEach((el) => { el.textContent = ''; });
        };

        const placeErrors = (errors) => {
            Object.entries(errors || {}).forEach(([key, messages]) => {
                const msg = (messages || []).join(', ');
                const itemMatch = key.match(/^items\.(\d+)\.(.+)$/);
                if (itemMatch) {
                    const el = document.getElementById(`error_items_${itemMatch[1]}_${itemMatch[2]}`);
                    if (el) el.textContent = msg;
                    else document.getElementById('error_items').textContent = msg;
                    return;
                }
                const el = document.getElementById(`error_${key}`);
                if (el) el.textContent = msg;
            });
        };

        addBtn.addEventListener('click', () => addRow());
        lookupBtn.addEventListener('click', lookupResi);
        scanInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                lookupResi();
            }
        });

        body.addEventListener('click', (event) => {
            const btn = event.target.closest('.btn-remove-row');
            if (!btn) return;
            btn.closest('tr')?.remove();
            refreshIndexes();
        });

        body.addEventListener('input', (event) => {
            const target = event.target;
            if (!target.matches('[data-name="qty_resi"], [data-name="qty_received"], [data-name="qty_good"], [data-name="qty_damaged"]')) return;
            syncRow(target.closest('tr'), target.dataset.name);
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            if (!resiNoInput.value && scanInput.value.trim()) {
                resiNoInput.value = scanInput.value.trim();
            }
            if (!body.querySelector('tr')) {
                document.getElementById('error_items').textContent = 'Minimal 1 item diperlukan.';
                return;
            }
            saveBtn.setAttribute('data-kt-indicator', 'on');
            saveBtn.disabled = true;
            try {
                const res = await fetch(submitUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                });
                const text = await res.text();
                let json = {};
                try { json = JSON.parse(text); } catch (err) {}
                if (!res.ok) {
                    if (json.errors) placeErrors(json.errors);
                    if (window.Swal) Swal.fire('Error', json.message || 'Gagal menyimpan retur', 'error');
                    return;
                }
                if (window.Swal) {
                    await Swal.fire('Berhasil', json.message || 'Retur berhasil disimpan', 'success');
                }
                window.location.href = backUrl;
            } catch (err) {
                if (window.Swal) Swal.fire('Error', 'Gagal menyimpan retur', 'error');
            } finally {
                saveBtn.removeAttribute('data-kt-indicator');
                saveBtn.disabled = false;
            }
        });

        if (initialResi) showResi(initialResi);
        if (initialItems.length) {
            initialItems.forEach((item) => addRow(item));
        } else {
            addRow();
        }

        if (window.flatpickr) {
            flatpickr('#transacted_at', { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
        }
    });
</script>
@endpush
