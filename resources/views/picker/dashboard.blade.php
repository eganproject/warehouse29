@extends('layouts.mobile')

@section('title', 'Menu Picker')

@section('content')
<style>
    .menu-card {
        display: flex;
        gap: 14px;
        align-items: center;
        padding: 16px;
        border-radius: 18px;
        border: 1px solid var(--border);
        background: #fff;
        text-decoration: none;
        color: inherit;
        box-shadow: var(--shadow);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .menu-card:active {
        transform: scale(0.99);
    }
    .menu-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        font-weight: 700;
        color: #0f172a;
        background: linear-gradient(135deg, rgba(15,118,110,0.18), rgba(16,185,129,0.15));
        border: 1px solid rgba(15,118,110,0.2);
    }
    .menu-icon.opname {
        background: linear-gradient(135deg, rgba(14,165,233,0.2), rgba(56,189,248,0.2));
        border-color: rgba(14,165,233,0.25);
    }
    .menu-title {
        font-weight: 700;
        font-size: 15px;
        margin-bottom: 4px;
    }
    .menu-desc {
        font-size: 12px;
        color: var(--muted);
    }
    .menu-list {
        display: grid;
        gap: 14px;
        margin-top: 10px;
    }
    .welcome-card {
        background: #ffffff;
        border-radius: var(--radius);
        border: 1px solid rgba(226, 232, 240, 0.7);
        box-shadow: var(--shadow);
        padding: 16px;
        margin-bottom: 16px;
    }
    .welcome-title {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 6px;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">Gudang 29</div>
            <div class="subtitle">Dashboard Picker</div>
        </div>
        <form method="POST" action="{{ $routes['logout'] }}">
            @csrf
            <button type="submit" class="logout">Logout</button>
        </form>
    </div>

    <div class="welcome-card">
        <div class="welcome-title">Pilih Menu Kerja</div>
        <div class="muted">Pilih proses yang ingin Anda lakukan hari ini.</div>
    </div>

    <div class="menu-list">
        <a class="menu-card" href="{{ $routes['opname'] }}">
            <div class="menu-icon opname">SO</div>
            <div>
                <div class="menu-title">Stock Opname</div>
                <div class="menu-desc">Input hasil stock opname dengan cepat.</div>
            </div>
        </a>

        @if(!empty($showPicking))
            <a class="menu-card" href="{{ $routes['picker'] }}">
                <div class="menu-icon">PK</div>
                <div>
                    <div class="menu-title">Picking</div>
                    <div class="menu-desc">Input barang yang dibawa oleh picker.</div>
                </div>
            </a>
        @endif
    </div>
</div>
@endsection
