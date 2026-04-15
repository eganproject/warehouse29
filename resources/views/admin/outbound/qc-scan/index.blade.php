@extends('layouts.admin')

@section('title', 'QC Scan Input')
@section('page_title', 'QC Scan Input')

@section('content')
<style>
    .qc-scan-input {
        font-size: 20px;
        letter-spacing: 0.5px;
        height: 58px;
    }
    .qc-scan-qty {
        height: 58px;
        font-size: 18px;
    }
    .qc-scan-status {
        border-radius: 14px;
        padding: 12px 14px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        color: #334155;
        min-height: 48px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .qc-scan-status .dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #94a3b8;
        flex: 0 0 auto;
    }
    .qc-scan-status.ok .dot { background: #16a34a; }
    .qc-scan-status.err .dot { background: #ef4444; }
    .qc-scan-status.warn .dot { background: #f59e0b; }
    .qc-scan-status .text {
        font-size: 13px;
        line-height: 1.4;
    }
    .qc-mini-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .qc-items-table td {
        vertical-align: middle;
    }
    .qc-qty-controls {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .qc-qty-controls .btn {
        padding: 0.35rem 0.6rem;
    }
    .qc-qty-controls input {
        width: 92px;
        text-align: center;
    }
    .qc-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #e5e7eb;
        padding: 8px 10px;
        border-radius: 999px;
        background: #ffffff;
    }
</style>

<div class="row g-6">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="fw-bold">Scan Barang (SKU)</div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex gap-2">
                        <a href="{{ $routes['pickingList'] }}" class="btn btn-sm btn-light">Picking List</a>
                        <a href="{{ $routes['pickerTransit'] }}" class="btn btn-sm btn-light">QC Transit</a>
                    </div>
                </div>
            </div>
            <div class="card-body py-6">
                <div class="text-muted fs-7 mb-5">
                    Scan SKU (input barang) untuk menambah <strong>QC Transit</strong>.
                    Scan out resi nantinya akan mengurangi qty di QC Transit.
                    <span class="d-inline-block ms-1">Tanggal kerja: <span class="fw-bold">{{ $today }}</span></span>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Scan SKU</label>
                    <input type="text" class="form-control form-control-solid qc-scan-input qc-mini-mono" id="qc_scan_code" autocomplete="off" placeholder="Arahkan scanner ke barcode SKU lalu Enter" />
                    <div class="form-text">Tips: pastikan cursor aktif di input ini saat mulai scan.</div>
                </div>

                <div class="row g-3">
                    <div class="col-4">
                        <label class="form-label fw-bold">Qty</label>
                        <input type="number" min="1" class="form-control form-control-solid qc-scan-qty" id="qc_scan_qty" value="1" />
                    </div>
                    <div class="col-8">
                        <label class="form-label fw-bold">&nbsp;</label>
                        <button type="button" class="btn btn-primary w-100 h-100" id="qc_scan_btn">
                            <i class="fa-solid fa-barcode me-2"></i>Scan
                        </button>
                    </div>
                </div>

                <div class="mt-4 qc-scan-status" id="qc_scan_status">
                    <span class="dot"></span>
                    <div class="text">Siap scan SKU.</div>
                </div>

                <div class="separator my-6"></div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-light-primary flex-grow-1" id="qc_btn_start" title="Memuat sesi draft untuk tanggal hari ini. Jika belum ada, sistem akan membuat sesi baru.">
                        <i class="fa-solid fa-arrows-rotate me-2"></i>Muat Sesi Draft Hari Ini
                    </button>
                    <button type="button" class="btn btn-success flex-grow-1" id="qc_btn_submit" disabled>
                        <i class="fa-solid fa-check me-2"></i>Selesaikan
                    </button>
                    <button type="button" class="btn btn-light flex-grow-1" id="qc_btn_focus">
                        <i class="fa-solid fa-crosshairs me-2"></i>Fokus Scanner
                    </button>
                </div>
                <div class="text-muted fs-8 mt-2">
                    Jika belum ada sesi draft untuk tanggal <span class="fw-bold">{{ $today }}</span>, sistem akan membuat sesi baru otomatis.
                </div>
            </div>
        </div>

        <div class="card mt-6">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="fw-bold">Quick Lookup Picking List</div>
                </div>
            </div>
            <div class="card-body py-6">
                <div class="text-muted fs-7 mb-3">Cek qty target & sisa picking list untuk SKU tertentu (opsional).</div>
                <input type="text" class="form-control form-control-solid" id="pl_lookup" placeholder="Ketik SKU lalu Enter" autocomplete="off" />
                <div class="mt-4" id="pl_lookup_result">
                    <div class="text-muted fs-7">Belum ada SKU yang dicek.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="fw-bold">Sesi QC Scan</div>
                </div>
                <div class="card-toolbar">
                    <a href="{{ $routes['dashboard'] }}" class="btn btn-sm btn-light">Dashboard</a>
                </div>
            </div>

            <div class="card-body py-6">
                <div class="d-flex flex-wrap gap-3 mb-6">
                    <div class="qc-pill">
                        <span class="text-muted fs-8">Status</span>
                        <span class="badge badge-light" id="qc_session_status">Belum mulai</span>
                    </div>
                    <div class="qc-pill">
                        <span class="text-muted fs-8">Kode</span>
                        <span class="fw-bold qc-mini-mono" id="qc_session_code">-</span>
                    </div>
                    <div class="qc-pill">
                        <span class="text-muted fs-8">Mulai</span>
                        <span class="fw-bold" id="qc_session_started_at">-</span>
                    </div>
                    <div class="qc-pill">
                        <span class="text-muted fs-8">Total SKU</span>
                        <span class="fw-bold" id="qc_total_items">0</span>
                    </div>
                    <div class="qc-pill">
                        <span class="text-muted fs-8">Total Qty</span>
                        <span class="fw-bold" id="qc_total_qty">0</span>
                    </div>
                    <div class="qc-pill">
                        <span class="text-muted fs-8">Akun</span>
                        <span class="fw-bold">{{ auth()->user()->name ?? '-' }}</span>
                        <span class="text-muted fs-8">{{ auth()->user()->email ?? '' }}</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle qc-items-table" id="qc_items_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="18%">SKU</th>
                                <th>Nama</th>
                                <th width="18%">Lokasi</th>
                                <th width="22%" class="text-end">Qty</th>
                                <th width="10%" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="qc_items_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-8">Belum ada item pada sesi.</td>
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
    const routes = @json($routes);
    const todayStr = '{{ $today }}';
    const csrfToken = '{{ csrf_token() }}';

    const state = {
        session: null,
        busy: false,
    };

    const el = {
        scanCode: document.getElementById('qc_scan_code'),
        scanQty: document.getElementById('qc_scan_qty'),
        scanBtn: document.getElementById('qc_scan_btn'),
        scanStatus: document.getElementById('qc_scan_status'),
        btnStart: document.getElementById('qc_btn_start'),
        btnSubmit: document.getElementById('qc_btn_submit'),
        btnFocus: document.getElementById('qc_btn_focus'),

        sessionStatus: document.getElementById('qc_session_status'),
        sessionCode: document.getElementById('qc_session_code'),
        sessionStartedAt: document.getElementById('qc_session_started_at'),
        totalItems: document.getElementById('qc_total_items'),
        totalQty: document.getElementById('qc_total_qty'),
        itemsBody: document.getElementById('qc_items_body'),

        pickingLookup: document.getElementById('pl_lookup'),
        pickingLookupResult: document.getElementById('pl_lookup_result'),
    };

    const setBusy = (busy) => {
        state.busy = !!busy;
        if (el.scanBtn) el.scanBtn.disabled = busy;
        if (el.btnStart) el.btnStart.disabled = busy;
        if (el.btnSubmit) el.btnSubmit.disabled = busy || !(state.session && state.session.status === 'draft');
    };

    const setScanStatus = (message, type = 'muted') => {
        if (!el.scanStatus) return;
        el.scanStatus.classList.remove('ok', 'err', 'warn');
        if (type === 'ok') el.scanStatus.classList.add('ok');
        if (type === 'err') el.scanStatus.classList.add('err');
        if (type === 'warn') el.scanStatus.classList.add('warn');
        const textEl = el.scanStatus.querySelector('.text');
        if (textEl) textEl.textContent = message || '';
    };

    const focusScanner = () => {
        if (!el.scanCode) return;
        el.scanCode.focus();
        el.scanCode.select?.();
    };

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
            credentials: 'same-origin',
            ...options,
        });

        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (err) { json = null; }

        if (!res.ok) {
            let message = json?.message || 'Terjadi kesalahan';
            if (json?.errors) {
                const first = Object.values(json.errors)[0];
                if (Array.isArray(first) && first.length) {
                    message = first[0];
                }
            }
            throw new Error(message);
        }
        return json;
    };

    const renderSession = () => {
        const session = state.session;
        const isDraft = !!session && session.status === 'draft';

        if (!session) {
            el.sessionStatus.textContent = 'Belum mulai';
            el.sessionStatus.className = 'badge badge-light';
            el.sessionCode.textContent = '-';
            el.sessionStartedAt.textContent = '-';
            el.totalItems.textContent = '0';
            el.totalQty.textContent = '0';
            el.btnSubmit.disabled = true;
            renderItems([]);
            return;
        }

        el.sessionStatus.textContent = isDraft ? 'Draft aktif' : 'Sesi selesai';
        el.sessionStatus.className = isDraft ? 'badge badge-light-success' : 'badge badge-light-warning';
        el.sessionCode.textContent = session.code || '-';
        el.sessionStartedAt.textContent = session.started_at || '-';

        const items = Array.isArray(session.items) ? session.items : [];
        const totalQty = items.reduce((sum, row) => sum + (Number(row.qty) || 0), 0);
        el.totalItems.textContent = String(items.length);
        el.totalQty.textContent = String(totalQty);
        el.btnSubmit.disabled = !isDraft;
        renderItems(items);
    };

    const esc = (value) => {
        return String(value ?? '').replace(/[&<>"']/g, (m) => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        }[m]));
    };

    const renderItems = (items) => {
        if (!el.itemsBody) return;
        if (!Array.isArray(items) || items.length === 0) {
            el.itemsBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-8">Belum ada item pada sesi.</td>
                </tr>
            `;
            return;
        }

        el.itemsBody.innerHTML = items.map((row) => {
            const address = row.address && String(row.address).trim() ? row.address : '-';
            return `
                <tr data-id="${row.id}">
                    <td class="qc-mini-mono fw-bold">${esc(row.sku || '-')}</td>
                    <td>
                        <div class="fw-bold">${esc(row.name || '-')}</div>
                        <div class="text-muted fs-8">Item ID: ${esc(row.item_id || '-')}</div>
                    </td>
                    <td>${esc(address)}</td>
                    <td class="text-end">
                        <div class="qc-qty-controls justify-content-end">
                            <button type="button" class="btn btn-sm btn-light qc-qty-btn" data-action="dec">-</button>
                            <input type="number" min="1" class="form-control form-control-solid form-control-sm qc-qty-input" value="${Number(row.qty) || 1}" />
                            <button type="button" class="btn btn-sm btn-light qc-qty-btn" data-action="inc">+</button>
                        </div>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-light-danger qc-btn-remove" title="Hapus item">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const refreshSession = async () => {
        const json = await fetchJson(routes.qcCurrent);
        state.session = json.session || null;
        renderSession();
    };

    const startSession = async () => {
        if (state.session && state.session.status === 'draft') {
            const code = state.session.code ? ` (${state.session.code})` : '';
            setScanStatus(`Sesi draft sudah aktif${code}. Silakan scan SKU.`, 'ok');
            focusScanner();
            return true;
        }

        setBusy(true);
        setScanStatus('Memuat sesi draft hari ini...', 'warn');
        try {
            const json = await fetchJson(routes.qcStart, { method: 'POST' });
            state.session = json.session || null;
            renderSession();

            const code = state.session?.code ? ` (${state.session.code})` : '';
            setScanStatus(`Sesi draft siap${code}. Silakan scan SKU.`, 'ok');
            return !!(state.session && state.session.status === 'draft');
        } catch (e) {
            setScanStatus(e.message || 'Gagal memuat sesi draft.', 'err');
            window.AppSwal?.error?.(e.message || 'Gagal memuat sesi draft.');
            return false;
        } finally {
            setBusy(false);
            focusScanner();
        }
    };

    const scanSku = async () => {
        if (state.busy) return;
        const code = (el.scanCode?.value || '').trim();
        const qty = Math.max(1, parseInt(el.scanQty?.value || '1', 10) || 1);
        if (!code) {
            setScanStatus('SKU kosong. Silakan scan ulang.', 'warn');
            focusScanner();
            return;
        }

        if (!state.session || state.session.status !== 'draft') {
            const ok = await startSession();
            if (!ok) return;
        }

        setBusy(true);
        setScanStatus(`Memproses scan ${code} (qty ${qty})...`, 'warn');
        try {
            const payload = new FormData();
            payload.append('code', code);
            payload.append('qty', String(qty));

            const json = await fetchJson(routes.qcScanItem, {
                method: 'POST',
                body: payload,
            });

            state.session = json.session || null;
            renderSession();
            setScanStatus(`OK: ${code} +${qty}`, 'ok');
            if (el.scanCode) el.scanCode.value = '';
            if (el.scanQty) el.scanQty.value = '1';
        } catch (e) {
            setScanStatus(e.message || 'Gagal memproses scan.', 'err');
            window.AppSwal?.error?.(e.message || 'Gagal memproses scan.');
        } finally {
            setBusy(false);
            focusScanner();
        }
    };

    const updateItemQty = async (rowId, qty) => {
        if (!rowId) return;
        const safeQty = Math.max(1, parseInt(qty, 10) || 1);
        setBusy(true);
        try {
            const payload = new FormData();
            payload.append('_method', 'PUT');
            payload.append('qty', String(safeQty));
            const url = routes.qcItemsUpdate.replace(':id', rowId);
            const json = await fetchJson(url, { method: 'POST', body: payload });
            state.session = json.session || null;
            renderSession();
            setScanStatus('Qty tersimpan.', 'ok');
        } catch (e) {
            setScanStatus(e.message || 'Gagal update qty.', 'err');
            window.AppSwal?.error?.(e.message || 'Gagal update qty.');
        } finally {
            setBusy(false);
            focusScanner();
        }
    };

    const removeItem = async (rowId) => {
        if (!rowId) return;
        const ok = await (window.AppSwal?.confirm?.('Hapus item dari sesi ini?') ?? Promise.resolve(confirm('Hapus item dari sesi ini?')));
        if (!ok) {
            focusScanner();
            return;
        }

        setBusy(true);
        try {
            const payload = new FormData();
            payload.append('_method', 'DELETE');
            const url = routes.qcItemsDestroy.replace(':id', rowId);
            const json = await fetchJson(url, { method: 'POST', body: payload });
            state.session = json.session || null;
            renderSession();
            setScanStatus('Item dihapus.', 'ok');
        } catch (e) {
            setScanStatus(e.message || 'Gagal hapus item.', 'err');
            window.AppSwal?.error?.(e.message || 'Gagal hapus item.');
        } finally {
            setBusy(false);
            focusScanner();
        }
    };

    const submitSession = async () => {
        if (!state.session || state.session.status !== 'draft') {
            setScanStatus('Tidak ada sesi draft untuk diselesaikan.', 'warn');
            return;
        }

        const ok = await (window.AppSwal?.confirm?.('Selesaikan sesi QC scan ini?') ?? Promise.resolve(confirm('Selesaikan sesi QC scan ini?')));
        if (!ok) {
            focusScanner();
            return;
        }

        setBusy(true);
        setScanStatus('Mengunci sesi...', 'warn');
        try {
            const json = await fetchJson(routes.qcSubmit, { method: 'POST' });
            state.session = json.session || null;
            renderSession();
            setScanStatus('Sesi selesai.', 'ok');

            if (window.Swal) {
                const items = state.session?.items || [];
                const totalQty = items.reduce((sum, row) => sum + (Number(row.qty) || 0), 0);
                window.Swal.fire({
                    icon: 'success',
                    title: 'Sesi QC Scan Selesai',
                    html: `
                        <div style="text-align:left; font-size:14px;">
                            Kode sesi: <strong>${esc(state.session?.code || '-')}</strong><br>
                            Total SKU: <strong>${items.length}</strong><br>
                            Total Qty: <strong>${totalQty}</strong>
                        </div>
                    `,
                    confirmButtonText: 'OK',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-primary' },
                });
            }
        } catch (e) {
            setScanStatus(e.message || 'Gagal menyelesaikan sesi.', 'err');
            window.AppSwal?.error?.(e.message || 'Gagal menyelesaikan sesi.');
        } finally {
            setBusy(false);
            focusScanner();
        }
    };

    const renderPickingLookup = (payload, querySku) => {
        if (!el.pickingLookupResult) return;
        const items = payload?.items || [];
        const sku = (querySku || '').trim();
        if (!sku) {
            el.pickingLookupResult.innerHTML = '<div class="text-muted fs-7">Belum ada SKU yang dicek.</div>';
            return;
        }

        const row = items.find((it) => String(it.sku || '').toLowerCase() === sku.toLowerCase());
        if (!row) {
            el.pickingLookupResult.innerHTML = `
                <div class="alert alert-light-warning mb-0">
                    SKU <span class="fw-bold qc-mini-mono">${esc(sku)}</span> tidak ada di picking list tanggal ${esc(payload?.date || todayStr)} (atau ter-filter exception).
                </div>
            `;
            return;
        }

        const remaining = Number(row.remaining_qty) || 0;
        const badge = remaining > 0 ? 'badge-light-warning' : 'badge-light-success';
        el.pickingLookupResult.innerHTML = `
            <div class="alert alert-light mb-0">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="fw-bold qc-mini-mono">${esc(row.sku)}</div>
                    <div class="text-muted">${esc(row.name || '-')}</div>
                    <div class="ms-auto">
                        <span class="badge badge-light-primary me-2">Target: ${Number(row.qty) || 0}</span>
                        <span class="badge ${badge}">Sisa: ${remaining}</span>
                    </div>
                </div>
            </div>
        `;
    };

    const lookupPickingList = async () => {
        const sku = (el.pickingLookup?.value || '').trim();
        if (!sku) return;
        try {
            const params = new URLSearchParams({ date: todayStr, q: sku });
            const json = await fetchJson(`${routes.pickingListData}?${params.toString()}`);
            renderPickingLookup(json, sku);
        } catch (e) {
            window.AppSwal?.error?.(e.message || 'Gagal cek picking list.');
        } finally {
            focusScanner();
        }
    };

    document.addEventListener('DOMContentLoaded', async () => {
        setScanStatus('Memuat sesi...', 'warn');
        try {
            await refreshSession();
            if (state.session && state.session.status === 'draft') {
                const code = state.session.code ? ` (${state.session.code})` : '';
                setScanStatus(`Sesi draft aktif${code}. Silakan scan SKU.`, 'ok');
            } else {
                setScanStatus('Belum ada sesi draft hari ini. Scan SKU untuk membuat sesi otomatis, atau klik "Muat Sesi Draft Hari Ini".', 'warn');
            }
        } catch (e) {
            setScanStatus('Gagal memuat sesi.', 'err');
        } finally {
            focusScanner();
        }

        el.btnStart?.addEventListener('click', startSession);
        el.btnSubmit?.addEventListener('click', submitSession);
        el.btnFocus?.addEventListener('click', focusScanner);
        el.scanBtn?.addEventListener('click', scanSku);
        el.scanCode?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                scanSku();
            }
        });
        el.pickingLookup?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupPickingList();
            }
        });

        document.getElementById('qc_items_table')?.addEventListener('click', (e) => {
            const tr = e.target?.closest?.('tr[data-id]');
            if (!tr) return;
            const rowId = tr.getAttribute('data-id');

            const removeBtn = e.target?.closest?.('.qc-btn-remove');
            if (removeBtn) {
                removeItem(rowId);
                return;
            }

            const qtyBtn = e.target?.closest?.('.qc-qty-btn');
            if (qtyBtn) {
                const action = qtyBtn.getAttribute('data-action');
                const input = tr.querySelector('.qc-qty-input');
                const current = Math.max(1, parseInt(input?.value || '1', 10) || 1);
                const next = action === 'dec' ? Math.max(1, current - 1) : current + 1;
                if (input) input.value = String(next);
                updateItemQty(rowId, next);
                return;
            }
        });

        document.getElementById('qc_items_table')?.addEventListener('change', (e) => {
            const input = e.target?.closest?.('.qc-qty-input');
            if (!input) return;
            const tr = input.closest('tr[data-id]');
            if (!tr) return;
            const rowId = tr.getAttribute('data-id');
            updateItemQty(rowId, input.value);
        });
    });
</script>
@endpush
