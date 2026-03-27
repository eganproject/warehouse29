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
</style>

<div class="card mb-8">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <div class="fw-bold fs-4">Ringkasan Resi Hari Ini</div>
                <div class="text-muted">Tanggal {{ $today ?? '-' }}</div>
            </div>
            <span class="kurir-badge">Perhari Berjalan</span>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Resi</div>
                <div class="stat-value">{{ number_format($totalResi ?? 0) }}</div>
                <div class="stat-meta">Resi diimport hari ini</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Scan Out</div>
                <div class="stat-value">{{ number_format($totalScanOut ?? 0) }}</div>
                <div class="stat-meta">Hasil scan out hari ini</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="fw-bold fs-4">Per Kurir</div>
            <div class="text-muted">Jumlah resi & hasil scan hari ini</div>
        </div>

        @if(isset($kurirs) && $kurirs->count())
            <div class="kurir-grid">
                @foreach($kurirs as $kurir)
                    <div class="stat-card">
                        <div class="kurir-name">{{ $kurir['name'] }}</div>
                        <div class="kurir-ratio">
                            <span class="ratio-resi">{{ number_format($kurir['resi_total']) }}</span>
                            <span class="ratio-sep">/</span>
                            <span class="ratio-scan">{{ number_format($kurir['scan_total']) }}</span>
                        </div>
                        <div class="kurir-remaining">
                            Sisa resi: {{ number_format($kurir['remaining']) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-muted">Belum ada data kurir.</div>
        @endif
    </div>
</div>
@endsection
