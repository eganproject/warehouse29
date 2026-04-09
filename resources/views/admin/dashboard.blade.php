@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }
    .kurir-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    @media (max-width: 991px) {
        .kurir-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 640px) {
        .kurir-grid {
            grid-template-columns: 1fr;
        }
    }
    .stat-card {
        border: 1px solid var(--bs-gray-200);
        border-radius: 16px;
        padding: 16px;
        background: #fff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    }
    .stat-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .stat-value {
        font-size: 28px;
        font-weight: 800;
        margin-top: 6px;
    }
    .stat-meta {
        font-size: 12px;
        color: #6b7280;
        margin-top: 6px;
    }
    .kurir-name {
        font-weight: 700;
        font-size: 15px;
        margin-bottom: 8px;
    }
    .kurir-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px;
    }
    .kurir-updated {
        font-size: 11px;
        color: #9ca3af;
        white-space: nowrap;
    }
    .kurir-ratio {
        font-size: 28px;
        font-weight: 800;
        margin-top: 6px;
        letter-spacing: -0.02em;
    }
    .ratio-resi {
        color: #1d4ed8;
    }
    .ratio-scan {
        color: #047857;
    }
    .ratio-sep {
        color: #9ca3af;
        padding: 0 4px;
        font-weight: 600;
    }
    .kurir-remaining {
        font-size: 12px;
        color: #b45309;
        margin-top: 6px;
        font-weight: 600;
    }
    .kurir-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(14, 116, 144, 0.12);
        color: #0e7490;
    }
    .kurir-actions {
        margin-top: 14px;
    }
    .kurir-detail-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--bs-primary);
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        border-bottom: 1px solid transparent;
        padding-bottom: 1px;
        transition: color 0.15s ease, border-color 0.15s ease;
    }
    .kurir-detail-link:hover,
    .kurir-detail-link:focus {
        color: #1d4ed8;
        border-bottom-color: currentColor;
        text-decoration: none;
    }
    .kurir-detail-link:focus-visible {
        outline: 2px solid rgba(37, 99, 235, 0.3);
        outline-offset: 2px;
    }
    .kurir-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .kurir-summary-item {
        min-width: 120px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid var(--bs-gray-200);
    }
    .kurir-summary-label {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: .04em;
    }
    .kurir-summary-value {
        font-size: 20px;
        font-weight: 800;
        margin-top: 4px;
    }
    .info-tip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 999px;
        border: 1px solid var(--bs-gray-300);
        color: #6b7280;
        cursor: help;
        vertical-align: middle;
    }
    .info-tip svg {
        display: block;
    }
</style>

<div class="card mb-8">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="fw-bold fs-4">Ringkasan Resi</div>
                <div class="text-muted">Tanggal {{ $today ?? '-' }}</div>
                <div class="text-muted fs-7 mt-1">
                    <span class="fw-semibold">Legenda:</span> Resi aktif = status selain cancel; Rasio = resi aktif / scan out; Resi cancel tidak masuk perhitungan.
                </div>
            </div>
            <form class="d-flex flex-wrap align-items-center gap-2" method="GET" action="{{ url()->current() }}">
                <span class="kurir-badge">Filter Tanggal</span>
                <input
                    type="text"
                    name="date"
                    id="filter_date"
                    class="form-control form-control-sm"
                    value="{{ $today ?? '' }}"
                />
                <button type="submit" class="btn btn-sm btn-light-primary">Terapkan</button>
                @if(request()->has('date'))
                    <a href="{{ url()->current() }}" class="btn btn-sm btn-light">Reset</a>
                @endif
            </form>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">
                    Total Resi Aktif
                    <span class="info-tip ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah resi dengan status selain cancel pada tanggal ini.">
                        <svg width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="4" cy="2" r="1" fill="currentColor"/>
                            <rect x="3" y="4" width="2" height="6" rx="1" fill="currentColor"/>
                        </svg>
                        <span class="visually-hidden">Info</span>
                    </span>
                </div>
                <div class="stat-value">{{ number_format($totalResi ?? 0) }}</div>
                <div class="stat-meta">Update: {{ $totalResiUpdated ?? '-' }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    Total Scan Out
                    <span class="info-tip ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah resi (berdasarkan tanggal upload) yang sudah scan out.">
                        <svg width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="4" cy="2" r="1" fill="currentColor"/>
                            <rect x="3" y="4" width="2" height="6" rx="1" fill="currentColor"/>
                        </svg>
                        <span class="visually-hidden">Info</span>
                    </span>
                </div>
                <div class="stat-value">{{ number_format($totalScanOut ?? 0) }}</div>
                <div class="stat-meta">Update: {{ $totalScanUpdated ?? '-' }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    Total Resi Cancel
                    <span class="info-tip ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah resi yang dibatalkan pada tanggal ini.">
                        <svg width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="4" cy="2" r="1" fill="currentColor"/>
                            <rect x="3" y="4" width="2" height="6" rx="1" fill="currentColor"/>
                        </svg>
                        <span class="visually-hidden">Info</span>
                    </span>
                </div>
                <div class="stat-value text-danger">{{ number_format($totalResiCanceled ?? 0) }}</div>
                <div class="stat-meta">Tidak termasuk resi aktif.</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="fw-bold fs-4">Per Kurir</div>
            <div class="text-muted">Jumlah resi & hasil scan tanggal terpilih</div>
        </div>
        <div class="text-muted fs-7 mb-4">Rasio menampilkan resi aktif / scan out per kurir.</div>

        @if(isset($kurirs) && $kurirs->count())
            <div class="kurir-grid">
                @foreach($kurirs as $kurir)
                    <div class="stat-card">
                        <div class="kurir-header">
                            <div class="kurir-name">{{ $kurir['name'] }}</div>
                            <div class="kurir-updated">Update: {{ $kurir['last_update'] }}</div>
                        </div>
                        <div class="kurir-ratio">
                            <span class="ratio-resi">{{ number_format($kurir['resi_total']) }}</span>
                            <span class="ratio-sep">/</span>
                            <span class="ratio-scan">{{ number_format($kurir['scan_total']) }}</span>
                        </div>
                        <div class="kurir-remaining">
                            Sisa resi aktif: {{ number_format($kurir['remaining']) }}
                        </div>
                        <div class="text-danger mt-1 fw-semibold" style="font-size: 12px;">
                            Canceled: {{ number_format($kurir['canceled_total'] ?? 0) }}
                        </div>
                        <div class="kurir-actions">
                            <span
                                role="button"
                                tabindex="0"
                                class="kurir-detail-link btn-kurir-detail"
                                data-kurir-id="{{ $kurir['id'] }}"
                                data-kurir-name="{{ $kurir['name'] }}"
                                data-date="{{ $today ?? '' }}"
                            >
                                Lihat Detail Resi
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-muted">Belum ada data kurir.</div>
        @endif
    </div>
</div>

<div class="modal fade" id="modal_kurir_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Detail Resi Kurir</h5>
                    <div class="text-muted fs-7" id="kurir_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="kurir-summary">
                    <div class="kurir-summary-item">
                        <div class="kurir-summary-label">Total Resi Aktif</div>
                        <div class="kurir-summary-value" id="kurir_detail_total">0</div>
                    </div>
                    <div class="kurir-summary-item">
                        <div class="kurir-summary-label">Sudah Scan Out</div>
                        <div class="kurir-summary-value text-success" id="kurir_detail_scanned">0</div>
                    </div>
                    <div class="kurir-summary-item">
                        <div class="kurir-summary-label">Belum Scan Out</div>
                        <div class="kurir-summary-value text-warning" id="kurir_detail_remaining">0</div>
                    </div>
                    <div class="kurir-summary-item">
                        <div class="kurir-summary-label">Canceled</div>
                        <div class="kurir-summary-value text-danger" id="kurir_detail_canceled">0</div>
                    </div>
                </div>
                <div class="text-muted fs-7 mb-3">
                    Keterangan: total resi aktif = status selain cancel. Daftar di bawah menampilkan resi aktif yang belum scan out.
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="28%">ID Pesanan</th>
                                <th width="28%">No Resi</th>
                                <th width="22%">Status</th>
                                <th width="22%">Tanggal Upload</th>
                            </tr>
                        </thead>
                        <tbody id="kurir_detail_body">
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
@endsection

@push('scripts')
<script>
    const kurirDetailUrl = '{{ route('admin.dashboard.kurir-detail') }}';

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                new bootstrap.Tooltip(el);
            });
        }
        const dateInput = document.getElementById('filter_date');
        if (typeof flatpickr !== 'undefined' && dateInput) {
            flatpickr(dateInput, { dateFormat: 'Y-m-d', allowInput: true });
        }
        const detailModalEl = document.getElementById('modal_kurir_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const detailSubtitle = document.getElementById('kurir_detail_subtitle');
        const detailTotal = document.getElementById('kurir_detail_total');
        const detailScanned = document.getElementById('kurir_detail_scanned');
        const detailRemaining = document.getElementById('kurir_detail_remaining');
        const detailCanceled = document.getElementById('kurir_detail_canceled');
        const detailBody = document.getElementById('kurir_detail_body');

        const setLoadingState = (kurirName, date) => {
            if (detailSubtitle) {
                detailSubtitle.textContent = `${kurirName || '-'} | Tanggal ${date || '-'}`;
            }
            if (detailTotal) detailTotal.textContent = '0';
            if (detailScanned) detailScanned.textContent = '0';
            if (detailRemaining) detailRemaining.textContent = '0';
            if (detailCanceled) detailCanceled.textContent = '0';
            if (detailBody) {
                detailBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
        };

        const renderRows = (rows) => {
            if (!detailBody) {
                return;
            }
            if (!Array.isArray(rows) || !rows.length) {
                detailBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-6">Tidak ada data resi yang belum scan out.</td>
                    </tr>
                `;
                return;
            }

            detailBody.innerHTML = rows.map((row) => `
                <tr>
                    <td>${row.id_pesanan || '-'}</td>
                    <td>${row.no_resi || '-'}</td>
                    <td>${row.status || '-'}</td>
                    <td>${row.tanggal_upload || '-'}</td>
                </tr>
            `).join('');
        };

        document.querySelectorAll('.btn-kurir-detail').forEach((button) => {
            button.addEventListener('click', async () => {
                const kurirId = button.getAttribute('data-kurir-id');
                const kurirName = button.getAttribute('data-kurir-name') || '-';
                const date = button.getAttribute('data-date') || '';

                if (!kurirId || !detailModal) {
                    return;
                }

                setLoadingState(kurirName, date);
                detailModal.show();

                try {
                    const params = new URLSearchParams({
                        kurir_id: kurirId,
                        date,
                    });
                    const response = await fetch(`${kurirDetailUrl}?${params.toString()}`);
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload?.message || 'Gagal memuat detail kurir.');
                    }

                    const meta = payload?.meta || {};
                    if (detailSubtitle) {
                        detailSubtitle.textContent = `${meta.kurir_name || kurirName} | Tanggal ${meta.date || date || '-'}`;
                    }
                    if (detailTotal) detailTotal.textContent = Number(meta.total_resi || 0).toLocaleString('id-ID');
                    if (detailScanned) detailScanned.textContent = Number(meta.scanned_total || 0).toLocaleString('id-ID');
                    if (detailRemaining) detailRemaining.textContent = Number(meta.remaining_total || 0).toLocaleString('id-ID');
                    if (detailCanceled) detailCanceled.textContent = Number(meta.canceled_total || 0).toLocaleString('id-ID');
                    renderRows(payload?.data || []);
                } catch (error) {
                    if (detailBody) {
                        detailBody.innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center text-danger py-6">${error.message || 'Gagal memuat detail kurir.'}</td>
                            </tr>
                        `;
                    }
                }
            });
            button.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    button.click();
                }
            });
        });
    });
</script>
@endpush
