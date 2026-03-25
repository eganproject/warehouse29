@extends('layouts.mobile')

@section('title', 'Packer Scan Resi')

@section('content')
<style>
    .section-title {
        font-weight: 700;
        margin-bottom: 8px;
    }
    .scan-actions {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .scan-row {
        display: grid;
        gap: 8px;
        grid-template-columns: 1fr auto;
        align-items: center;
    }
    .scan-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
        border-radius: 12px;
        font-weight: 700;
        border: 1px solid var(--border);
        background: #fff;
    }
    .status-line {
        font-size: 12px;
        color: var(--muted);
        margin-top: 6px;
    }
    .result-card {
        display: none;
    }
    .result-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }
    .result-badge {
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(16, 185, 129, 0.15);
        color: #047857;
        font-weight: 700;
        font-size: 11px;
    }
    .result-items {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .result-item {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 10px 12px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 13px;
    }
    .result-meta {
        font-size: 12px;
        color: var(--muted);
    }
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar-actions form {
        margin: 0;
    }
    .scanner-modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.72);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        z-index: 50;
    }
    .scanner-card {
        width: 100%;
        max-width: 520px;
        background: #fff;
        border-radius: 18px;
        padding: 14px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        display: grid;
        gap: 10px;
    }
    .scanner-video {
        width: 100%;
        border-radius: 14px;
        background: #111827;
    }
    .scanner-actions {
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }
    .scanner-actions .primary-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">Gudang 29</div>
            <div class="subtitle">Packer Scan Resi</div>
        </div>
        <div class="topbar-actions">
            <a href="{{ $routes['dashboard'] }}" class="logout">Dashboard</a>
            <form method="POST" action="{{ $routes['logout'] }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Scan Resi</div>
        <div class="muted">Pilih jenis kode yang akan discan, lalu proses untuk mengurangi sisa transit.</div>
        <div class="scan-actions">
            <select class="input" id="scan_type">
                <option value="no_resi">No Resi</option>
                <option value="id_pesanan">ID Pesanan</option>
            </select>
            <div class="scan-row">
                <input type="text" class="input" id="scan_code" placeholder="Scan No. Resi" autocomplete="off" />
                <button type="button" class="scan-btn" id="btn_open_scanner">Scan</button>
            </div>
            <button type="button" class="primary-btn" id="btn_scan">Proses Resi</button>
        </div>
        <div class="status-line" id="scan_status">Siap memproses resi.</div>
    </div>

    <div class="card result-card" id="result_card">
        <div class="result-header">
            <div>
                <div style="font-weight:700;" id="result_title">Resi Diproses</div>
                <div class="result-meta" id="result_meta">-</div>
            </div>
            <div class="result-badge">Sukses</div>
        </div>
        <div class="result-items" id="result_items"></div>
    </div>
</div>

<div class="scanner-modal" id="scanner_modal">
    <div class="scanner-card">
        <div style="font-weight:700;">Kamera Scanner</div>
        <video class="scanner-video" id="scanner_video" playsinline></video>
        <div class="scanner-actions">
            <button type="button" class="ghost-btn" id="btn_close_scanner">Tutup</button>
            <button type="button" class="primary-btn" id="btn_start_scan">Mulai Scan</button>
        </div>
        <div class="muted" id="scanner_hint">Arahkan kamera ke barcode resi.</div>
    </div>
</div>

<script>
    const routes = @json($routes);
    const csrfToken = '{{ csrf_token() }}';

    const el = {
        scanType: document.getElementById('scan_type'),
        scanCode: document.getElementById('scan_code'),
        btnScan: document.getElementById('btn_scan'),
        btnOpenScanner: document.getElementById('btn_open_scanner'),
        scanStatus: document.getElementById('scan_status'),
        resultCard: document.getElementById('result_card'),
        resultMeta: document.getElementById('result_meta'),
        resultItems: document.getElementById('result_items'),
        scannerModal: document.getElementById('scanner_modal'),
        scannerVideo: document.getElementById('scanner_video'),
        btnCloseScanner: document.getElementById('btn_close_scanner'),
        btnStartScan: document.getElementById('btn_start_scan'),
        scannerHint: document.getElementById('scanner_hint'),
    };

    let scannerStream = null;
    let scannerActive = false;
    let barcodeDetector = null;
    let scanLoopId = null;

    const setStatus = (text, type = 'muted') => {
        el.scanStatus.textContent = text;
        if (type === 'error') {
            el.scanStatus.style.color = '#b91c1c';
        } else if (type === 'success') {
            el.scanStatus.style.color = '#047857';
        } else if (type === 'pending') {
            el.scanStatus.style.color = '#f97316';
        } else {
            el.scanStatus.style.color = '#6b7280';
        }
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
            const error = new Error(json?.message || 'Terjadi kesalahan');
            if (json?.details) {
                error.details = json.details;
            }
            throw error;
        }

        return json;
    };

    const showError = (message, details = []) => {
        if (typeof Swal !== 'undefined') {
            let html = `<div style="text-align:left; font-size:13px;">${message}</div>`;
            if (Array.isArray(details) && details.length) {
                const list = details.map((row) => {
                    const sku = row.sku || '-';
                    const required = row.required ?? '-';
                    const available = row.available ?? '-';
                    const reason = row.reason ? `<div style="color:#64748b; font-size:12px;">${row.reason}</div>` : '';
                    const stock = row.available !== undefined
                        ? `<div style="color:#64748b; font-size:12px;">Butuh ${required}, tersedia ${available}</div>`
                        : `<div style="color:#64748b; font-size:12px;">Butuh ${required}</div>`;
                    return `<li style="margin-bottom:8px;"><strong>${sku}</strong>${reason}${stock}</li>`;
                }).join('');
                html += `<ul style="text-align:left; padding-left:18px; margin-top:8px;">${list}</ul>`;
            }
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                html,
            });
            return;
        }

        setStatus(message, 'error');
    };

    const renderResult = (data) => {
        const resi = data?.resi || {};
        const items = Array.isArray(data?.items) ? data.items : [];
        const resiLine = [
            resi.id_pesanan ? `ID Pesanan: ${resi.id_pesanan}` : null,
            resi.no_resi ? `No Resi: ${resi.no_resi}` : null,
            resi.tanggal_pesanan ? `Tanggal Order: ${resi.tanggal_pesanan}` : null,
        ].filter(Boolean).join(' • ');

        el.resultMeta.textContent = resiLine || '-';
        el.resultItems.innerHTML = items.map((row) => {
            const qty = row.qty ?? 0;
            return `<div class="result-item"><strong>${row.sku || '-'}</strong><span>${qty} qty</span></div>`;
        }).join('');

        el.resultCard.style.display = 'block';
    };

    const submitScan = async () => {
        const type = el.scanType.value;
        const code = el.scanCode.value.trim();
        if (!code) {
            setStatus('Masukkan nomor resi atau ID pesanan.', 'error');
            el.scanCode.focus();
            return;
        }

        el.btnScan.disabled = true;
        setStatus('Memproses resi...', 'pending');

        try {
            const data = await fetchJson(routes.scan, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ code, type }),
            });

            setStatus(data?.message || 'Resi berhasil diproses.', 'success');
            renderResult(data);
            el.scanCode.value = '';
            el.scanCode.focus();
        } catch (error) {
            showError(error.message || 'Gagal memproses resi.', error.details || []);
            setStatus(error.message || 'Gagal memproses resi.', 'error');
        } finally {
            el.btnScan.disabled = false;
        }
    };

    const stopScanner = () => {
        scannerActive = false;
        if (scanLoopId) {
            cancelAnimationFrame(scanLoopId);
            scanLoopId = null;
        }
        if (scannerStream) {
            scannerStream.getTracks().forEach((track) => track.stop());
            scannerStream = null;
        }
        el.scannerVideo.srcObject = null;
    };

    const closeScanner = () => {
        stopScanner();
        el.scannerModal.style.display = 'none';
        el.btnStartScan.disabled = false;
        el.scannerHint.textContent = 'Arahkan kamera ke barcode resi.';
    };

    const openScanner = async () => {
        if (!('BarcodeDetector' in window)) {
            showError('Browser belum mendukung scan kamera. Gunakan input manual.');
            return;
        }

        try {
            barcodeDetector = new BarcodeDetector({
                formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'upc_a', 'upc_e'],
            });
        } catch (error) {
            showError('Fitur scan tidak tersedia. Gunakan input manual.');
            return;
        }

        el.scannerModal.style.display = 'flex';
    };

    const startScanner = async () => {
        try {
            el.btnStartScan.disabled = true;
            el.scannerHint.textContent = 'Mengaktifkan kamera...';
            scannerStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                },
                audio: false,
            });
            el.scannerVideo.srcObject = scannerStream;
            await el.scannerVideo.play();
            scannerActive = true;
            el.scannerHint.textContent = 'Scan berjalan. Arahkan ke barcode.';
            scanLoop();
        } catch (error) {
            el.btnStartScan.disabled = false;
            showError('Tidak bisa membuka kamera. Pastikan izin kamera aktif.');
            closeScanner();
        }
    };

    const scanLoop = async () => {
        if (!scannerActive || !barcodeDetector) return;
        try {
            const barcodes = await barcodeDetector.detect(el.scannerVideo);
            if (Array.isArray(barcodes) && barcodes.length) {
                const code = barcodes[0].rawValue || '';
                if (code) {
                    el.scanCode.value = code;
                    el.scanCode.focus();
                    closeScanner();
                    return;
                }
            }
        } catch (error) {
            // ignore frame errors
        }
        scanLoopId = requestAnimationFrame(scanLoop);
    };

    el.btnScan.addEventListener('click', submitScan);
    el.btnOpenScanner.addEventListener('click', openScanner);
    el.btnCloseScanner.addEventListener('click', closeScanner);
    el.btnStartScan.addEventListener('click', startScanner);
    el.scannerModal.addEventListener('click', (event) => {
        if (event.target === el.scannerModal) {
            closeScanner();
        }
    });
    el.scanType.addEventListener('change', () => {
        const type = el.scanType.value;
        el.scanCode.placeholder = type === 'id_pesanan' ? 'Scan ID Pesanan' : 'Scan No. Resi';
        el.scanCode.focus();
    });
    el.scanCode.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitScan();
        }
    });
</script>
@endsection
