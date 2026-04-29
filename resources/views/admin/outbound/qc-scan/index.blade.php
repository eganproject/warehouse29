@extends('layouts.admin')

@section('title', 'QC Scan Input')
@section('page_title', 'QC Scan Input')

@section('content')
<style>
    /* ── Typography & Base ── */
    .qc-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }

    /* ── Status bar ── */
    .qc-status {
        border-radius: 12px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background 0.15s, border-color 0.15s;
    }
    .qc-status .dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #94a3b8;
        flex: 0 0 auto;
    }
    .qc-status .msg { font-size: 13px; line-height: 1.4; }
    .qc-status.ok  { background: #f0fdf4; border-color: #bbf7d0; }
    .qc-status.ok  .dot { background: #16a34a; }
    .qc-status.err { background: #fef2f2; border-color: #fecaca; }
    .qc-status.err .dot { background: #ef4444; }
    .qc-status.warn { background: #fffbeb; border-color: #fde68a; }
    .qc-status.warn .dot { background: #f59e0b; }

    /* ── Step badge ── */
    .step-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        font-size: 12px;
        font-weight: 700;
        flex: 0 0 auto;
    }
    .step-badge.active   { background: #3b82f6; color: #fff; }
    .step-badge.done     { background: #16a34a; color: #fff; }
    .step-badge.inactive { background: #e2e8f0; color: #94a3b8; }

    /* ── Resi info bar ── */
    .resi-bar {
        background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
        border: 1px solid #bfdbfe;
        border-radius: 14px;
        padding: 14px 18px;
    }
    .resi-bar .resi-no  { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; }
    .resi-bar .resi-id  { font-size: 12px; color: #64748b; margin-top: 2px; }
    .resi-bar .resi-meta { font-size: 12px; color: #475569; margin-top: 6px; }

    /* ── Progress bar ── */
    .qc-progress-wrap {
        background: #f1f5f9;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
    }
    .qc-progress-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #3b82f6, #06b6d4);
        transition: width 0.4s ease;
    }
    .qc-progress-fill.done { background: linear-gradient(90deg, #16a34a, #22c55e); }

    /* ── Checklist ── */
    .qc-checklist {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .qc-checklist-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 10px;
        transition: background 0.1s;
        cursor: default;
    }
    .qc-checklist-item.pending { background: #fff; border: 1px solid #e5e7eb; margin-bottom: 4px; }
    .qc-checklist-item.scanned { background: #f0fdf4; border: 1px solid #bbf7d0; margin-bottom: 4px; opacity: 0.85; }
    .qc-checklist-item.active  { background: #eff6ff; border: 1.5px solid #93c5fd; margin-bottom: 4px; }
    .qc-check-icon { font-size: 16px; flex: 0 0 auto; }
    .qc-sku-label { font-weight: 700; font-size: 13px; min-width: 90px; }
    .qc-sku-name  { font-size: 12px; color: #64748b; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .qc-qty-badge {
        margin-left: auto;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 999px;
        white-space: nowrap;
        flex: 0 0 auto;
    }
    .qc-qty-badge.pending { background: #f1f5f9; color: #64748b; }
    .qc-qty-badge.done    { background: #dcfce7; color: #15803d; }
    .qc-qty-badge.active  { background: #dbeafe; color: #1d4ed8; }

    /* ── Input large ── */
    .qc-input-lg {
        font-size: 18px;
        letter-spacing: 0.5px;
        height: 54px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .qc-input-md { height: 50px; font-size: 16px; }

    /* ── Session pills ── */
    .qc-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #e5e7eb;
        padding: 6px 10px;
        border-radius: 999px;
        background: #fff;
        font-size: 12px;
    }
    .qc-pill .label { color: #94a3b8; }
    .qc-pill .value { font-weight: 700; }

    /* ── Items table ── */
    .qc-items-table td { vertical-align: middle; }
    .qc-qty-controls { display: inline-flex; align-items: center; gap: 5px; }
    .qc-qty-controls .btn { padding: 0.3rem 0.55rem; }
    .qc-qty-controls input { width: 80px; text-align: center; }

    /* ── All-done banner ── */
    .qc-alldone-banner {
        background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
        border: 2px solid #86efac;
        border-radius: 14px;
        padding: 16px 20px;
        text-align: center;
    }

    /* ── Phase visibility ── */
    #phase-scan-resi { display: block; }
    #phase-scan-sku  { display: none; }
</style>

<div class="row g-6">
    {{-- ═══════════════ LEFT COLUMN ═══════════════ --}}
    <div class="col-xl-5">

        {{-- ─── PHASE 1: Scan Resi ─── --}}
        <div id="phase-scan-resi">
            <div class="card">
                <div class="card-header border-0 pt-6 pb-0">
                    <div class="card-title">
                        <div class="d-flex align-items-center gap-3">
                            <span class="step-badge active">1</span>
                            <div>
                                <div class="fw-bold fs-5">Scan Resi</div>
                                <div class="text-muted fs-8">Scan resi terlebih dahulu untuk melihat daftar SKU</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-toolbar">
                        <a href="{{ $routes['pickingList'] }}" class="btn btn-sm btn-light me-2">Picking List</a>
                        <a href="{{ $routes['pickerTransit'] }}" class="btn btn-sm btn-light">QC Transit</a>
                    </div>
                </div>
                <div class="card-body pt-5 pb-7">
                    <div class="alert alert-light-primary d-flex align-items-start gap-3 py-3 px-4 mb-6">
                        <i class="fa-solid fa-circle-info mt-1 text-primary"></i>
                        <div class="fs-8 text-gray-700">
                            Scan <strong>No Resi</strong> atau <strong>ID Pesanan</strong> terlebih dahulu.
                            Sistem akan menampilkan SKU yang harus di-QC sesuai isi resi tersebut.
                            <span class="d-block mt-1">Tanggal kerja: <strong>{{ $today }}</strong></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">Tipe Scan</label>
                        <select class="form-select form-select-solid qc-input-md" id="resi_type">
                            <option value="no_resi">No Resi</option>
                            <option value="id_pesanan">ID Pesanan</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">Kode Resi / ID Pesanan</label>
                        <input
                            type="text"
                            class="form-control form-control-solid qc-input-lg"
                            id="resi_code"
                            autocomplete="off"
                            placeholder="Arahkan scanner ke barcode resi, lalu Enter"
                        />
                        <div class="form-text">Pastikan cursor aktif di kolom ini saat mulai scan.</div>
                    </div>

                    <button type="button" class="btn btn-primary w-100 fs-6 py-3 mb-4" id="resi_scan_btn">
                        <i class="fa-solid fa-barcode me-2"></i>Cari &amp; Muat Resi
                    </button>

                    <div class="qc-status" id="resi_scan_status">
                        <span class="dot"></span>
                        <div class="msg">Siap scan resi.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── PHASE 2: Resi Loaded → Scan SKU ─── --}}
        <div id="phase-scan-sku">

            {{-- Resi Info Bar --}}
            <div class="resi-bar mb-4">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div class="flex-grow-1 min-w-0">
                        <div class="resi-no qc-mono" id="disp_no_resi">-</div>
                        <div class="resi-id" id="disp_id_pesanan">-</div>
                        <div class="resi-meta">
                            <i class="fa-solid fa-calendar-days me-1"></i><span id="disp_tanggal">-</span>
                            <span class="mx-2">·</span>
                            <i class="fa-solid fa-truck me-1"></i><span id="disp_kurir">-</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light flex-shrink-0" id="btn_change_resi" title="Ganti resi">
                        <i class="fa-solid fa-arrows-rotate me-1"></i>Ganti Resi
                    </button>
                </div>
            </div>

            {{-- Progress --}}
            <div class="card mb-4">
                <div class="card-body py-5 px-6">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-bold fs-7" id="progress_text">0 / 0 SKU selesai</div>
                        <div class="fw-bold fs-7 text-primary" id="progress_pct">0%</div>
                    </div>
                    <div class="qc-progress-wrap mb-5">
                        <div class="qc-progress-fill" id="progress_bar" style="width: 0%"></div>
                    </div>

                    {{-- All-done banner (hidden by default) --}}
                    <div class="qc-alldone-banner d-none" id="alldone_banner">
                        <div class="fs-3 mb-1">✅</div>
                        <div class="fw-bold text-success fs-6">Semua SKU pada resi ini sudah selesai di-scan!</div>
                        <div class="text-muted fs-8 mt-1">Kamu bisa scan resi lain atau selesaikan sesi QC.</div>
                    </div>

                    {{-- Checklist --}}
                    <div id="checklist_wrap">
                        <div class="fw-bold fs-8 text-uppercase text-muted ls-1 mb-3">Daftar SKU Resi</div>
                        <ul class="qc-checklist" id="sku_checklist"></ul>
                    </div>
                </div>
            </div>

            {{-- SKU Scan Form --}}
            <div class="card" id="sku_scan_card">
                <div class="card-header border-0 pt-5 pb-0">
                    <div class="card-title">
                        <div class="d-flex align-items-center gap-3">
                            <span class="step-badge active" id="step2_badge">2</span>
                            <div>
                                <div class="fw-bold">Scan SKU</div>
                                <div class="text-muted fs-8">Scan SKU untuk validasi kesesuaian isi paket</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-5 pb-6">
                    <div class="mb-4">
                        <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">Scan SKU</label>
                        <input
                            type="text"
                            class="form-control form-control-solid qc-input-lg"
                            id="sku_code"
                            autocomplete="off"
                            placeholder="Scan barcode SKU lalu Enter…"
                        />
                        <div class="form-text" id="sku_hint">Scan 1 item fisik per kali scan. Qty default: 1.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">Qty</label>
                            <input
                                type="number"
                                min="1"
                                class="form-control form-control-solid"
                                style="height:50px;font-size:17px;"
                                id="sku_qty"
                                value="1"
                            />
                        </div>
                        <div class="col-8">
                            <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">&nbsp;</label>
                            <button type="button" class="btn btn-primary w-100 h-100 fs-6" id="sku_scan_btn">
                                <i class="fa-solid fa-barcode me-2"></i>Scan SKU
                            </button>
                        </div>
                    </div>

                    <div class="qc-status mb-5" id="sku_scan_status">
                        <span class="dot"></span>
                        <div class="msg">Siap scan SKU.</div>
                    </div>

                    <div class="separator my-5"></div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-light" id="qc_btn_focus" title="Fokus ke input scanner">
                            <i class="fa-solid fa-crosshairs"></i>
                        </button>
                        <button type="button" class="btn btn-light-primary flex-grow-1" id="qc_btn_start">
                            <i class="fa-solid fa-arrows-rotate me-2"></i>Muat Sesi QC Hari Ini
                        </button>
                    </div>
                    <div class="text-muted fs-8 mt-2">
                        Tidak ada submit manual. Resi otomatis selesai saat seluruh SKU wajib sudah discan.
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══════════════ RIGHT COLUMN ═══════════════ --}}
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="fw-bold fs-5">Sesi QC Scan</div>
                </div>
                <div class="card-toolbar gap-2">
                    <a href="{{ $routes['dashboard'] }}" class="btn btn-sm btn-light">Dashboard</a>
                </div>
            </div>
            <div class="card-body py-5">
                {{-- Session pills --}}
                <div class="d-flex flex-wrap gap-2 mb-6">
                    <div class="qc-pill">
                        <span class="label">Status</span>
                        <span class="badge badge-light value" id="qc_session_status">Belum mulai</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Kode</span>
                        <span class="value qc-mono" id="qc_session_code">-</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Mulai</span>
                        <span class="value" id="qc_session_started_at">-</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Total SKU</span>
                        <span class="value" id="qc_total_items">0</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Total Qty</span>
                        <span class="value" id="qc_total_qty">0</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Akun</span>
                        <span class="value">{{ auth()->user()->name ?? '-' }}</span>
                    </div>
                </div>

                {{-- Items table --}}
                <div class="fw-bold fs-7 text-uppercase text-muted mb-3">Resi Belum Selesai QC</div>
                <div class="table-responsive mb-8">
                    <table class="table table-row-dashed align-middle qc-items-table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-8 text-uppercase gs-0">
                                <th>No Resi</th>
                                <th class="text-end">Scan / Wajib</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="qc_pending_resis_body">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-8">Tidak ada resi pending.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="fw-bold fs-7 text-uppercase text-muted mb-3">Akumulasi SKU Sesi</div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle qc-items-table" id="qc_items_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-8 text-uppercase gs-0">
                                <th width="20%">SKU</th>
                                <th>Nama</th>
                                <th width="20%" class="text-end">Scan / Wajib</th>
                            </tr>
                        </thead>
                        <tbody id="qc_items_body">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-10">
                                    <i class="fa-regular fa-clipboard fs-2 d-block mb-2 text-gray-300"></i>
                                    Belum ada item pada sesi.
                                </td>
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
    const routes   = @json($routes);
    const todayStr = '{{ $today }}';
    const csrf     = '{{ csrf_token() }}';

    /* ─────────────── STATE ─────────────── */
    const state = {
        phase: 'scan-resi',   // 'scan-resi' | 'scan-sku'
        session: null,
        busy: false,
        resi: null,           // {id, id_pesanan, no_resi, tanggal_pesanan, kurir_name}
        checklist: [],        // [{sku, qty, scanned_qty}]
        itemNames: {},        // sku → name (fetched from session items)
    };

    /* ─────────────── ELEMENT REFS ─────────────── */
    const el = {
        // Phase wrappers
        phaseScanResi: document.getElementById('phase-scan-resi'),
        phaseScanSku:  document.getElementById('phase-scan-sku'),

        // Step 1
        resiType:   document.getElementById('resi_type'),
        resiCode:   document.getElementById('resi_code'),
        resiScanBtn: document.getElementById('resi_scan_btn'),
        resiStatus:  document.getElementById('resi_scan_status'),

        // Resi bar
        dispNoResi:    document.getElementById('disp_no_resi'),
        dispIdPesanan: document.getElementById('disp_id_pesanan'),
        dispTanggal:   document.getElementById('disp_tanggal'),
        dispKurir:     document.getElementById('disp_kurir'),
        btnChangeResi: document.getElementById('btn_change_resi'),

        // Progress
        progressText: document.getElementById('progress_text'),
        progressPct:  document.getElementById('progress_pct'),
        progressBar:  document.getElementById('progress_bar'),
        alldoneBanner: document.getElementById('alldone_banner'),
        checklistWrap: document.getElementById('checklist_wrap'),
        skuChecklist:  document.getElementById('sku_checklist'),

        // Step 2
        skuCode:    document.getElementById('sku_code'),
        skuQty:     document.getElementById('sku_qty'),
        skuScanBtn: document.getElementById('sku_scan_btn'),
        skuStatus:  document.getElementById('sku_scan_status'),
        skuHint:    document.getElementById('sku_hint'),
        btnSubmit:  null,
        btnStart:   document.getElementById('qc_btn_start'),
        btnFocus:   document.getElementById('qc_btn_focus'),

        // Session panel
        sessionStatus:    document.getElementById('qc_session_status'),
        sessionCode:      document.getElementById('qc_session_code'),
        sessionStartedAt: document.getElementById('qc_session_started_at'),
        totalItems:       document.getElementById('qc_total_items'),
        totalQty:         document.getElementById('qc_total_qty'),
        itemsBody:        document.getElementById('qc_items_body'),
        pendingResisBody: document.getElementById('qc_pending_resis_body'),
    };

    /* ─────────────── AUDIO ─────────────── */
    const audio = { ctx: null };

    async function ensureAudio() {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        if (!audio.ctx) audio.ctx = new Ctx();
        if (audio.ctx.state === 'suspended') await audio.ctx.resume();
        return audio.ctx;
    }

    async function beep(kind) {
        try {
            const ctx = await ensureAudio();
            if (!ctx) return;
            const tones = kind === 'ok'
                ? [{ f: 880, t: 0, d: 0.07 }, { f: 1174, t: 0.09, d: 0.09 }]
                : [{ f: 320, t: 0, d: 0.12 }, { f: 240, t: 0.12, d: 0.16 }];
            const now = ctx.currentTime + 0.01;
            tones.forEach(({ f, t, d }) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = kind === 'ok' ? 'sine' : 'triangle';
                osc.frequency.setValueAtTime(f, now + t);
                gain.gain.setValueAtTime(0.0001, now + t);
                gain.gain.exponentialRampToValueAtTime(0.035, now + t + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + t + d);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + t);
                osc.stop(now + t + d);
            });
        } catch {}
    }

    /* ─────────────── HELPERS ─────────────── */
    const esc = v => String(v ?? '').replace(/[&<>"']/g, m =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));

    function setStatus(elRef, msg, type = '') {
        if (!elRef) return;
        elRef.classList.remove('ok', 'err', 'warn');
        if (type) elRef.classList.add(type);
        const d = elRef.querySelector('.msg');
        if (d) d.textContent = msg || '';
    }

    function setBusy(busy) {
        state.busy = !!busy;
        const btns = [el.resiScanBtn, el.skuScanBtn, el.btnStart];
        btns.forEach(b => { if (b) b.disabled = busy; });
        if (el.btnSubmit) el.btnSubmit.disabled = true;
    }

    function focusSku() {
        if (el.skuCode) { el.skuCode.focus(); el.skuCode.select?.(); }
    }
    function focusResi() {
        if (el.resiCode) { el.resiCode.focus(); el.resiCode.select?.(); }
    }

    async function fetchJson(url, opts = {}) {
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(opts.headers || {}) },
            credentials: 'same-origin',
            ...opts,
        });
        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch {}
        if (!res.ok) {
            let msg = json?.message || 'Terjadi kesalahan';
            if (json?.errors) {
                const first = Object.values(json.errors)[0];
                if (Array.isArray(first) && first.length) msg = first[0];
            }
            throw new Error(msg);
        }
        return json;
    }

    /* ─────────────── PHASE SWITCHING ─────────────── */
    function goToPhase(phase) {
        state.phase = phase;
        el.phaseScanResi.style.display = phase === 'scan-resi' ? 'block' : 'none';
        el.phaseScanSku.style.display  = phase === 'scan-sku'  ? 'block' : 'none';
        if (phase === 'scan-resi') {
            setStatus(el.resiStatus, 'Siap scan resi.');
            if (el.resiCode) el.resiCode.value = '';
            setTimeout(focusResi, 50);
        } else {
            setTimeout(focusSku, 50);
        }
    }

    /* ─────────────── CHECKLIST ─────────────── */
    function checklistProgress() {
        const total = state.checklist.length;
        const done  = state.checklist.filter(i => i.scanned_qty >= i.qty).length;
        const pct   = total > 0 ? Math.round(done / total * 100) : 0;
        return { total, done, pct };
    }

    function renderChecklist() {
        if (!el.skuChecklist) return;
        const items = state.checklist;
        if (!items.length) { el.skuChecklist.innerHTML = ''; return; }

        el.skuChecklist.innerHTML = items.map(item => {
            const isDone    = item.scanned_qty >= item.qty;
            const isPartial = item.scanned_qty > 0 && !isDone;
            const cls       = isDone ? 'scanned' : 'pending';
            const icon      = isDone ? '✅' : '⬜';
            const badgeCls  = isDone ? 'done' : 'pending';
            const qtyLabel  = isDone
                ? `${item.qty} / ${item.qty}`
                : (isPartial ? `${item.scanned_qty} / ${item.qty}` : `0 / ${item.qty}`);
            const name = state.itemNames[item.sku.toLowerCase()] || '';

            return `<li class="qc-checklist-item ${cls}" data-sku="${esc(item.sku)}">
                <span class="qc-check-icon">${icon}</span>
                <span class="qc-sku-label qc-mono">${esc(item.sku)}</span>
                <span class="qc-sku-name">${esc(name)}</span>
                <span class="qc-qty-badge ${badgeCls}">${qtyLabel}</span>
            </li>`;
        }).join('');
    }

    function updateProgress() {
        const { total, done, pct } = checklistProgress();
        if (el.progressText) el.progressText.textContent = `${done} / ${total} SKU selesai`;
        if (el.progressPct)  el.progressPct.textContent  = `${pct}%`;
        if (el.progressBar) {
            el.progressBar.style.width = `${pct}%`;
            el.progressBar.classList.toggle('done', pct === 100);
        }
        const allDone = total > 0 && done === total;
        if (el.alldoneBanner) el.alldoneBanner.classList.toggle('d-none', !allDone);
        if (el.checklistWrap) el.checklistWrap.classList.toggle('d-none', allDone);
        renderChecklist();
    }

    /* ─────────────── SESSION RENDER ─────────────── */
    function renderSession() {
        const s = state.session;
        const isDraft = !!s && s.status === 'active';

        if (!s) {
            el.sessionStatus.textContent = 'Belum mulai';
            el.sessionStatus.className = 'badge badge-light value';
            el.sessionCode.textContent = '-';
            el.sessionStartedAt.textContent = '-';
            el.totalItems.textContent = '0';
            el.totalQty.textContent   = '0';
            renderItems([]);
            renderPendingResis([]);
            return;
        }

        el.sessionStatus.textContent = isDraft ? 'Aktif' : 'Tidak aktif';
        el.sessionStatus.className   = isDraft
            ? 'badge badge-light-success value'
            : 'badge badge-light-warning value';
        el.sessionCode.textContent      = s.code || '-';
        el.sessionStartedAt.textContent = s.started_at || '-';

        const items = Array.isArray(s.items) ? s.items : [];
        const total = items.reduce((sum, r) => sum + (Number(r.qty) || 0), 0);
        el.totalItems.textContent = String(items.length);
        el.totalQty.textContent   = String(total);

        // Update item names cache from session
        items.forEach(r => {
            if (r.sku && r.name) state.itemNames[r.sku.toLowerCase()] = r.name;
        });

        renderItems(items);
        renderPendingResis(Array.isArray(s.resis) ? s.resis : []);
    }

    function renderPendingResis(resis) {
        if (!el.pendingResisBody) return;
        const pending = (Array.isArray(resis) ? resis : []).filter(row => row.status !== 'completed');
        if (!pending.length) {
            el.pendingResisBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-8">Tidak ada resi pending.</td>
                </tr>`;
            return;
        }

        el.pendingResisBody.innerHTML = pending.map((row, idx) => {
            const pct = Number(row.progress || 0);
            const label = row.no_resi || row.id_pesanan || '-';
            return `<tr>
                <td>
                    <div class="fw-bold qc-mono text-primary fs-8">${esc(label)}</div>
                    <div class="text-muted fs-9">${esc(row.id_pesanan || '-')} · ${Number(row.items?.length || 0)} SKU · ${pct}%</div>
                </td>
                <td class="text-end fw-semibold">${Number(row.scanned_qty || 0).toLocaleString('id-ID')} / ${Number(row.required_qty || 0).toLocaleString('id-ID')}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-light-primary btn-resume-resi" data-id="${row.id}">Lanjutkan</button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderItems(items) {
        if (!el.itemsBody) return;
        if (!Array.isArray(items) || !items.length) {
            el.itemsBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-10">
                        <i class="fa-regular fa-clipboard fs-2 d-block mb-2 text-gray-300"></i>
                        Belum ada item pada sesi.
                    </td>
                </tr>`;
            return;
        }
        el.itemsBody.innerHTML = items.map(row => {
            const addr = row.address && String(row.address).trim() ? row.address : '-';
            return `<tr>
                <td class="qc-mono fw-bold fs-8">${esc(row.sku || '-')}</td>
                <td>
                    <div class="fw-bold fs-8">${esc(row.name || '-')}</div>
                </td>
                <td class="text-end fw-semibold">${Number(row.qty || 0).toLocaleString('id-ID')} / ${Number(row.required_qty || 0).toLocaleString('id-ID')}</td>
            </tr>`;
        }).join('');
    }

    /* ─────────────── API CALLS ─────────────── */
    async function refreshSession() {
        const json = await fetchJson(routes.qcCurrent);
        state.session = json.session || null;
        renderSession();
    }

    async function startSession() {
        if (state.session?.status === 'active') return true;
        setBusy(true);
        try {
            const json = await fetchJson(routes.qcStart, { method: 'POST' });
            state.session = json.session || null;
            renderSession();
            return !!(state.session?.status === 'active');
        } catch (e) {
            setStatus(el.skuStatus, e.message || 'Gagal memuat sesi.', 'err');
            window.AppSwal?.error?.(e.message);
            return false;
        } finally {
            setBusy(false);
        }
    }

    /* ─────────────── STEP 1: LOOKUP RESI ─────────────── */
    async function lookupResi() {
        if (state.busy) return;
        const type = el.resiType?.value || 'no_resi';
        const code = (el.resiCode?.value || '').trim();

        if (!code) {
            setStatus(el.resiStatus, 'Kode resi tidak boleh kosong.', 'warn');
            focusResi();
            return;
        }

        setBusy(true);
        setStatus(el.resiStatus, `Mencari resi "${code}"…`, 'warn');
        try {
            await ensureAudio();
            const params = new URLSearchParams({ type, code });
            const json = await fetchJson(`${routes.qcResiLookup}?${params}`);


            state.resi      = json.resi;
            state.checklist = (json.items || []).map(i => ({
                sku: i.sku,
                qty: i.qty,
                scanned_qty: i.scanned_qty || 0,  // dipulihkan dari history session
            }));

            // Load item names from current session
            if (state.session?.items) {
                state.session.items.forEach(r => {
                    if (r.sku && r.name) state.itemNames[r.sku.toLowerCase()] = r.name;
                });
            }

            // Fill display
            el.dispNoResi.textContent    = state.resi.no_resi    || '-';
            el.dispIdPesanan.textContent = `ID Pesanan: ${state.resi.id_pesanan || '-'}`;
            el.dispTanggal.textContent   = state.resi.tanggal_pesanan || '-';
            el.dispKurir.textContent     = state.resi.kurir_name || '-';

            // Handle resi yang sudah pernah di-scan
            if (json.already_scanned) {
                if (json.is_complete) {
                    // Resi SELESAI - tolak, tidak bisa scan ulang
                    beep('err');
                    if (window.Swal) {
                        await window.Swal.fire({
                            icon: 'error',
                            title: 'Resi Sudah Selesai Di-QC',
                            html: `Resi <strong class="font-monospace">${esc(state.resi.no_resi || code)}</strong> sudah selesai di-scan pada sesi ini.<br><br>
                                <span class="text-muted fs-7">Semua SKU telah terverifikasi secara fisik. Tidak perlu scan ulang.</span>`,
                            confirmButtonText: 'OK',
                            buttonsStyling: false,
                            customClass: { confirmButton: 'btn btn-danger' },
                        });
                    }
                    state.resi      = null;
                    state.checklist = [];
                    if (el.resiCode) el.resiCode.value = '';
                    setStatus(el.resiStatus, 'Resi sudah selesai di-scan. Silakan scan resi lain.', 'warn');
                    focusResi();
                    return;
                }
                // Resi BELUM SELESAI - lanjutkan dari history
                const doneSKUs  = state.checklist.filter(i => i.scanned_qty >= i.qty).length;
                const totalSKUs = state.checklist.length;
                beep('ok');
                swalToast('info', 'Melanjutkan Scan Resi',
                    `Resi ini pernah di-scan (${doneSKUs}/${totalSKUs} SKU selesai). Progress dipulihkan.`,
                    4000);
            } else {
                beep('ok');
            }

            // Catat resi ke ledger QC. Ini wajib supaya scan SKU bisa diaudit per resi.
            const form = new FormData();
            form.append('resi_id', String(state.resi.id));
            await fetchJson(routes.qcResiRecord, { method: 'POST', body: form });
            await refreshSession();

            updateProgress();
            goToPhase('scan-sku');
            setStatus(el.skuStatus,
                json.already_scanned
                    ? 'Melanjutkan scan. Lihat progress checklist di atas.'
                    : 'Resi dimuat. Scan SKU sesuai daftar di atas.',
                'ok');
        } catch (e) {
            setStatus(el.resiStatus, e.message || 'Resi tidak ditemukan.', 'err');
            beep('err');
            window.AppSwal?.error?.(e.message || 'Resi tidak ditemukan.');
            focusResi();
        } finally {
            setBusy(false);
        }
    }

    /* ─────────────── SWEET ALERT HELPERS ─────────────── */
    function swalToast(icon, title, text, timer = 2500) {
        if (!window.Swal) return;
        window.Swal.fire({
            icon,
            title,
            text,
            toast: true,
            position: 'top-end',
            timer,
            timerProgressBar: true,
            showConfirmButton: false,
        });
    }

    /* ─────────────── STEP 2: SCAN SKU ─────────────── */
    async function scanSku() {
        if (state.busy) return;
        const code = (el.skuCode?.value || '').trim();
        let qty    = Math.max(1, parseInt(el.skuQty?.value || '1', 10) || 1);

        if (!code) {
            setStatus(el.skuStatus, 'Kode SKU kosong. Scan ulang.', 'warn');
            focusSku();
            return;
        }

        // ── Validasi 1: SKU harus ada di checklist resi ──
        const checkItem = state.checklist.find(i => i.sku.toLowerCase() === code.toLowerCase());
        if (!checkItem) {
            const msg = `SKU "${code}" tidak ada dalam resi ini. Pastikan barang sesuai dengan resi yang di-scan.`;
            setStatus(el.skuStatus, msg, 'err');
            beep('err');
            if (window.Swal) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'SKU Tidak Sesuai Resi',
                    html: `SKU <strong class="font-monospace">${esc(code)}</strong> tidak ada dalam resi <strong>${esc(state.resi?.no_resi || '-')}</strong>.<br><br>
                        <span class="text-muted fs-7">Pastikan barang yang di-scan sesuai dengan resi yang dimuat.</span>`,
                    confirmButtonText: 'Mengerti',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-danger' },
                });
            }
            if (el.skuCode) el.skuCode.value = '';
            focusSku();
            return;
        }

        // ── Validasi 2: SKU sudah selesai di-scan ──
        if (checkItem.scanned_qty >= checkItem.qty) {
            const msg = `SKU "${code}" sudah selesai di-scan (${checkItem.qty} dari ${checkItem.qty} qty).`;
            setStatus(el.skuStatus, msg, 'warn');
            beep('err');
            swalToast('warning', 'SKU Sudah Selesai', msg, 3000);
            if (el.skuCode) el.skuCode.value = '';
            focusSku();
            return;
        }

        // ── Validasi 3: Qty tidak boleh melebihi sisa resi ──
        const remaining = checkItem.qty - checkItem.scanned_qty;
        if (qty > remaining) {
            qty = remaining;
            if (el.skuQty) el.skuQty.value = String(qty);
            setStatus(el.skuStatus, `Qty disesuaikan ke ${qty} — hanya ${remaining} lagi dibutuhkan untuk SKU ini.`, 'warn');
        }

        // ── Pastikan sesi ada ──
        if (!state.session || state.session.status !== 'active') {
            const ok = await startSession();
            if (!ok) { focusSku(); return; }
        }

        setBusy(true);
        setStatus(el.skuStatus, `Memproses scan ${code} (qty ${qty})…`, 'warn');
        try {
            const form = new FormData();
            form.append('code', code);
            form.append('qty', String(qty));
            // Kirim resi_id agar backend bisa validasi SKU memang ada di resi ini
            if (state.resi?.id) form.append('resi_id', String(state.resi.id));

            const json = await fetchJson(routes.qcScanItem, { method: 'POST', body: form });
            state.session = json.session || null;
            renderSession();

            // Update checklist scanned qty
            checkItem.scanned_qty = Math.min(checkItem.qty, checkItem.scanned_qty + qty);
            const sessionItem = state.session?.items?.find(i => i.sku?.toLowerCase() === code.toLowerCase());
            if (sessionItem?.name) state.itemNames[code.toLowerCase()] = sessionItem.name;

            updateProgress();

            const sisaSetelahScan = checkItem.qty - checkItem.scanned_qty;
            const skuSelesai      = sisaSetelahScan <= 0;

            if (skuSelesai) {
                // ── Notifikasi: SKU selesai ──
                const doneMsg = `SKU "${code}" sudah selesai di-scan (${checkItem.qty} dari ${checkItem.qty} qty).`;
                setStatus(el.skuStatus, `✅ ${doneMsg}`, 'ok');
                beep('ok');
                swalToast('success', 'SKU Selesai ✅', doneMsg, 3000);

                // ── Notifikasi: Semua SKU di resi selesai ──
                const { total, done } = checklistProgress();
                if (total > 0 && done === total) {
                    // Beri jeda agar toast "SKU selesai" sempat tampil dulu
                    setTimeout(() => {
                        if (!window.Swal) return;
                        window.Swal.fire({
                            icon: 'success',
                            title: '🎉 Semua SKU Selesai!',
                            html: `<div style="text-align:left;font-size:14px">
                                Resi <strong class="font-monospace">${esc(state.resi?.no_resi || '-')}</strong> sudah selesai di-scan.<br>
                                Semua <strong>${total}</strong> SKU telah diverifikasi secara fisik.<br><br>
                                <span class="text-muted">Scan resi lain atau selesaikan sesi QC.</span>
                            </div>`,
                            confirmButtonText: '🔄 Scan Resi Lain',
                            showCancelButton: true,
                            cancelButtonText: 'Tetap di Sini',
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: 'btn btn-primary me-2',
                                cancelButton: 'btn btn-light',
                            },
                        }).then(result => {
                            if (result.isConfirmed) goToPhase('scan-resi');
                        });
                    }, 600);
                }
            } else {
                const msg = `OK: ${code} +${qty} ✓  (sisa perlu: ${sisaSetelahScan})`;
                setStatus(el.skuStatus, msg, 'ok');
                beep('ok');
            }

            if (el.skuCode) el.skuCode.value = '';
            if (el.skuQty)  el.skuQty.value  = '1';
        } catch (e) {
            setStatus(el.skuStatus, e.message || 'Gagal memproses scan.', 'err');
            beep('err');
            if (window.Swal) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'Scan Gagal',
                    text: e.message || 'Gagal memproses scan.',
                    confirmButtonText: 'OK',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-danger' },
                });
            }
        } finally {
            setBusy(false);
            focusSku();
        }
    }


    /* ─────────────── SKU INPUT HANDLER (info saja, qty tidak diubah) ─────────────── */
    let skuInputTimer = null;
    function onSkuInput() {
        clearTimeout(skuInputTimer);
        skuInputTimer = setTimeout(() => {
            const code = (el.skuCode?.value || '').trim();
            if (!code || !state.checklist.length) {
                if (el.skuHint) el.skuHint.textContent = 'Scan 1 item fisik per kali scan. Qty default: 1.';
                return;
            }
            const item = state.checklist.find(i => i.sku.toLowerCase() === code.toLowerCase());
            if (item) {
                const remaining = item.qty - item.scanned_qty;
                if (item.scanned_qty >= item.qty) {
                    if (el.skuHint) el.skuHint.textContent = `✅ SKU ini sudah selesai (${item.qty}/${item.qty} qty).`;
                } else {
                    if (el.skuHint) el.skuHint.textContent = `SKU ada di resi — terscan: ${item.scanned_qty}/${item.qty}, sisa: ${remaining}. Max qty per scan: ${remaining}.`;
                }
            } else {
                if (el.skuHint) el.skuHint.textContent = '⚠ SKU ini tidak ada dalam resi yang dimuat — scan akan ditolak.';
            }
        }, 120);
    }

    function resumePendingResi(qcResiId) {
        const row = (state.session?.resis || []).find(r => String(r.id) === String(qcResiId));
        if (!row) return;

        state.resi = {
            id: row.resi_id,
            no_resi: row.no_resi,
            id_pesanan: row.id_pesanan,
            tanggal_pesanan: row.tanggal_pesanan,
            kurir_name: row.kurir_name,
        };
        state.checklist = (row.items || []).map(item => ({
            sku: item.sku,
            qty: Number(item.required_qty || 0),
            scanned_qty: Number(item.scanned_qty || 0),
        }));
        (row.items || []).forEach(item => {
            if (item.sku && item.name) state.itemNames[item.sku.toLowerCase()] = item.name;
        });

        el.dispNoResi.textContent = state.resi.no_resi || '-';
        el.dispIdPesanan.textContent = `ID Pesanan: ${state.resi.id_pesanan || '-'}`;
        el.dispTanggal.textContent = state.resi.tanggal_pesanan || '-';
        el.dispKurir.textContent = state.resi.kurir_name || '-';
        updateProgress();
        goToPhase('scan-sku');
        setStatus(el.skuStatus, 'Melanjutkan resi pending. Scan SKU yang belum lengkap.', 'ok');
    }

    /* ─────────────── INIT ─────────────── */
    document.addEventListener('DOMContentLoaded', async () => {
        // Load existing session
        try {
            await refreshSession();
        } catch {}

        // Start at phase 1
        goToPhase('scan-resi');
        focusResi();

        // ── Step 1 listeners ──
        el.resiScanBtn?.addEventListener('click', async () => {
            await ensureAudio();
            await lookupResi();
        });
        el.resiCode?.addEventListener('keydown', async e => {
            if (e.key === 'Enter') { e.preventDefault(); await ensureAudio(); await lookupResi(); }
        });

        // ── Resi bar listeners ──
        el.btnChangeResi?.addEventListener('click', () => goToPhase('scan-resi'));

        // ── Step 2 listeners ──
        el.skuCode?.addEventListener('input', onSkuInput);
        el.skuScanBtn?.addEventListener('click', async () => {
            await ensureAudio();
            await scanSku();
        });
        el.skuCode?.addEventListener('keydown', async e => {
            if (e.key === 'Enter') { e.preventDefault(); await ensureAudio(); await scanSku(); }
        });

        el.btnStart?.addEventListener('click', async () => {
            await ensureAudio();
            await startSession();
            setStatus(el.skuStatus,
                state.session?.status === 'active'
                    ? `Sesi QC aktif (${state.session.code}). Silakan scan SKU.`
                    : 'Gagal memuat sesi.',
                state.session?.status === 'active' ? 'ok' : 'err');
            focusSku();
        });
        el.btnFocus?.addEventListener('click', async () => {
            await ensureAudio();
            focusSku();
        });

        el.pendingResisBody?.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-resume-resi');
            if (!btn) return;
            await ensureAudio();
            resumePendingResi(btn.getAttribute('data-id'));
        });
    });
</script>
@endpush
