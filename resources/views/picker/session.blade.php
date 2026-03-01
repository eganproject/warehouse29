@extends('layouts.mobile')

@section('title', 'Picker Mobile')

@section('content')
<style>
    .address-line {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        margin-top: 4px;
        font-size: 11px;
        color: var(--muted);
        line-height: 1.4;
    }
    .address-tag {
        display: inline-flex;
        align-items: center;
        padding: 2px 6px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 600;
        background: rgba(15, 118, 110, 0.12);
        color: var(--brand);
        white-space: nowrap;
    }
    .address-text {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .result-info .address-line {
        margin-top: 6px;
    }
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar-actions form {
        margin: 0;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">Gudang 29</div>
            <div class="subtitle">Picker Mobile Input</div>
        </div>
        <div class="topbar-actions">
            <a href="{{ $routes['dashboard'] }}" class="logout">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="card" id="session_card">
        <div class="session-meta">
            <div>
                <span class="badge" id="session_status">Belum Mulai</span>
            </div>
            <div class="code" id="session_code">-</div>
        </div>
        <div class="muted" id="session_started">Mulai input untuk membuat sesi baru.</div>
        <div style="margin-top: 12px;">
            <button type="button" class="primary-btn" id="btn_start">Mulai Input</button>
        </div>
    </div>

    <div class="card" id="search_card">
        <div style="font-weight: 700; margin-bottom: 8px;">Tambah Barang</div>
        <input type="text" class="input" id="item_search" placeholder="Cari SKU atau nama barang" autocomplete="off" />
        <div class="results" id="search_results"></div>
    </div>

    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <div style="font-weight:700;">Daftar Barang Dibawa</div>
            <div class="muted" id="total_items">0 item</div>
        </div>
        <div class="muted" id="items_empty">Belum ada barang ditambahkan.</div>
        <div class="items-list" id="items_list"></div>
    </div>
</div>

<div class="bottom-bar">
    <div class="bottom-inner">
        <div>
            <div class="summary">
                <strong id="total_qty">0 qty</strong>
                Total akumulasi barang
            </div>
            <div class="save-status" id="save_status">Siap diinput</div>
        </div>
        <button class="primary-btn" id="btn_submit" style="width:auto; padding:12px 18px;">Selesaikan</button>
    </div>
</div>

<script>
    const routes = @json($routes);
    const initialSession = @json($session);
    const csrfToken = '{{ csrf_token() }}';

    const state = {
        session: initialSession,
        searching: false,
    };

    const el = {
        sessionStatus: document.getElementById('session_status'),
        sessionCode: document.getElementById('session_code'),
        sessionStarted: document.getElementById('session_started'),
        btnStart: document.getElementById('btn_start'),
        itemSearch: document.getElementById('item_search'),
        searchResults: document.getElementById('search_results'),
        itemsList: document.getElementById('items_list'),
        itemsEmpty: document.getElementById('items_empty'),
        totalItems: document.getElementById('total_items'),
        totalQty: document.getElementById('total_qty'),
        saveStatus: document.getElementById('save_status'),
        btnSubmit: document.getElementById('btn_submit'),
        searchCard: document.getElementById('search_card'),
    };

    const setSaveStatus = (text, pending = false) => {
        el.saveStatus.textContent = text;
        el.saveStatus.style.color = pending ? '#f97316' : '#6b7280';
    };

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
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
            const error = new Error(message);
            if (json?.insufficient) {
                error.insufficient = json.insufficient;
            }
            throw error;
        }
        return json;
    };

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

    const renderSession = () => {
        const session = state.session;
        if (!session) {
            el.sessionStatus.textContent = 'Belum Mulai';
            el.sessionStatus.style.background = 'rgba(148,163,184,0.15)';
            el.sessionStatus.style.color = '#64748b';
            el.sessionCode.textContent = '-';
            el.sessionStarted.textContent = 'Mulai input untuk membuat sesi baru.';
            el.btnStart.textContent = 'Mulai Input';
            el.btnSubmit.classList.add('disabled');
            el.searchCard.classList.add('disabled');
            renderItems([]);
            return;
        }

        const isDraft = session.status === 'draft';
        el.sessionStatus.textContent = isDraft ? 'Draft Aktif' : 'Sesi Selesai';
        el.sessionStatus.style.background = isDraft ? 'rgba(15,118,110,0.12)' : 'rgba(249,115,22,0.15)';
        el.sessionStatus.style.color = isDraft ? '#0f766e' : '#c2410c';
        el.sessionCode.textContent = session.code || '-';
        el.sessionStarted.textContent = `Mulai: ${session.started_at || '-'}`;
        el.btnStart.textContent = isDraft ? 'Lanjutkan Input' : 'Mulai Sesi Baru';
        el.btnSubmit.classList.toggle('disabled', !isDraft);
        el.searchCard.classList.toggle('disabled', !isDraft);
        renderItems(session.items || []);
    };

    const renderItems = (items) => {
        const totalQty = items.reduce((sum, row) => sum + (row.qty || 0), 0);
        el.totalQty.textContent = `${totalQty} qty`;
        el.totalItems.textContent = `${items.length} item`;

        if (!items.length) {
            el.itemsEmpty.style.display = 'block';
            el.itemsList.innerHTML = '';
            return;
        }

        el.itemsEmpty.style.display = 'none';
        el.itemsList.innerHTML = items.map((row) => {
            const address = row.address && row.address.trim() ? row.address : 'Belum diisi';
            return `
                <div class="item-row" data-id="${row.id}" data-qty="${row.qty}">
                    <div class="item-meta">
                        <strong>${row.sku || '-'} • ${row.name || '-'}</strong>
                        <div class="address-line">
                            <span class="address-tag">Lokasi</span>
                            <span class="address-text">${address}</span>
                        </div>
                        <span>Qty tercatat: ${row.qty}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="qty-controls">
                            <button class="qty-btn" data-action="dec">-</button>
                            <input type="number" min="1" class="qty-input" value="${row.qty}" />
                            <button class="qty-btn" data-action="inc">+</button>
                        </div>
                        <button class="remove-btn" data-action="remove">×</button>
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderSearchResults = (items) => {
        if (!items.length) {
            el.searchResults.innerHTML = '';
            return;
        }
        el.searchResults.innerHTML = items.map(item => {
            const address = item.address && item.address.trim() ? item.address : 'Belum diisi';
            return `
                <div class="result-item">
                    <div class="result-info">
                        <strong>${item.sku}</strong>
                        <span>${item.name}</span>
                        <div class="address-line">
                            <span class="address-tag">Lokasi</span>
                            <span class="address-text">${address}</span>
                        </div>
                    </div>
                    <button class="add-btn" data-id="${item.id}">Tambah</button>
                </div>
            `;
        }).join('');
    };

    const refreshSession = async () => {
        const json = await fetchJson(routes.current);
        state.session = json.session;
        renderSession();
    };

    const startSession = async () => {
        if (state.session && state.session.status === 'draft') {
            renderSession();
            setSaveStatus('Batch aktif digunakan');
            return;
        }
        try {
            setSaveStatus('Membuat sesi...', true);
            const json = await fetchJson(routes.start, { method: 'POST' });
            state.session = json.session;
            renderSession();
            setSaveStatus('Sesi siap digunakan');
        } catch (err) {
            setSaveStatus(err.message || 'Gagal membuat sesi');
        }
    };

    const addItem = async (itemId) => {
        try {
            setSaveStatus('Menyimpan item...', true);
            const payload = new FormData();
            payload.append('item_id', itemId);
            payload.append('qty', 1);
            const json = await fetchJson(routes.itemsStore, {
                method: 'POST',
                body: payload,
            });
            state.session = json.session;
            renderSession();
            setSaveStatus('Item tersimpan');
        } catch (err) {
            setSaveStatus(err.message || 'Gagal menyimpan item');
        }
    };

    const updateItem = async (rowId, qty) => {
        if (!rowId) return;
        if (qty < 1) {
            return removeItem(rowId);
        }
        try {
            setSaveStatus('Memperbarui qty...', true);
            const payload = new FormData();
            payload.append('_method', 'PUT');
            payload.append('qty', qty);
            const json = await fetchJson(routes.itemsUpdate.replace(':id', rowId), {
                method: 'POST',
                body: payload,
            });
            state.session = json.session;
            renderSession();
            setSaveStatus('Perubahan tersimpan');
        } catch (err) {
            setSaveStatus(err.message || 'Gagal memperbarui qty');
        }
    };

    const removeItem = async (rowId) => {
        try {
            setSaveStatus('Menghapus item...', true);
            const payload = new FormData();
            payload.append('_method', 'DELETE');
            const json = await fetchJson(routes.itemsDestroy.replace(':id', rowId), {
                method: 'POST',
                body: payload,
            });
            state.session = json.session;
            renderSession();
            setSaveStatus('Item dihapus');
        } catch (err) {
            setSaveStatus(err.message || 'Gagal menghapus item');
        }
    };

    const submitSession = async () => {
        try {
            setSaveStatus('Mengunci sesi...', true);
            const json = await fetchJson(routes.submit, { method: 'POST' });
            state.session = json.session;
            renderSession();
            setSaveStatus('Sesi selesai dikirim');
            if (typeof Swal !== 'undefined') {
                const items = state.session?.items || [];
                const totalQty = items.reduce((sum, row) => sum + (row.qty || 0), 0);
                Swal.fire({
                    icon: 'success',
                    title: 'Sesi selesai',
                    html: `
                        <div style="text-align:left; font-size:14px;">
                            Kode sesi: <strong>${state.session?.code || '-'}</strong><br>
                            Total item: <strong>${items.length}</strong><br>
                            Total qty: <strong>${totalQty}</strong>
                        </div>
                    `,
                    confirmButtonText: 'OK',
                    allowOutsideClick: false,
                });
            }
        } catch (err) {
            if (err?.insufficient) {
                showInsufficientStock(err.insufficient);
                setSaveStatus('Stok tidak mencukupi');
                return;
            }
            setSaveStatus(err.message || 'Gagal mengunci sesi');
        }
    };

    const debounce = (fn, delay = 300) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    };

    el.btnStart.addEventListener('click', async () => {
        await startSession();
    });

    el.searchResults.addEventListener('click', async (e) => {
        const btn = e.target.closest('.add-btn');
        if (!btn) return;
        const itemId = btn.getAttribute('data-id');
        if (!itemId) return;
        await addItem(itemId);
        el.itemSearch.value = '';
        renderSearchResults([]);
    });

    el.itemsList.addEventListener('click', async (e) => {
        const row = e.target.closest('.item-row');
        if (!row) return;
        const rowId = row.getAttribute('data-id');
        const currentQty = parseInt(row.getAttribute('data-qty') || '1', 10);

        const action = e.target.getAttribute('data-action');
        if (action === 'inc') {
            await updateItem(rowId, currentQty + 1);
            return;
        }
        if (action === 'dec') {
            await updateItem(rowId, currentQty - 1);
            return;
        }
        if (action === 'remove') {
            await removeItem(rowId);
        }
    });

    el.itemsList.addEventListener('change', async (e) => {
        const input = e.target.closest('.qty-input');
        if (!input) return;
        const row = e.target.closest('.item-row');
        if (!row) return;
        const rowId = row.getAttribute('data-id');
        const qty = parseInt(input.value || '1', 10);
        await updateItem(rowId, qty);
    });

    el.btnSubmit.addEventListener('click', async () => {
        if (!state.session || state.session.status !== 'draft') return;
        await submitSession();
    });

    const runSearch = debounce(async () => {
        const q = el.itemSearch.value.trim();
        if (q.length < 2) {
            renderSearchResults([]);
            return;
        }
        try {
            setSaveStatus('Mencari item...', true);
            const url = `${routes.searchItems}?q=${encodeURIComponent(q)}`;
            const json = await fetchJson(url);
            renderSearchResults(json.items || []);
            setSaveStatus('Siap diinput');
        } catch (err) {
            renderSearchResults([]);
            setSaveStatus('Gagal mencari item');
        }
    }, 300);

    el.itemSearch.addEventListener('input', runSearch);

    renderSession();
</script>
@endsection
