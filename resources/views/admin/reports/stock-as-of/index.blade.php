@extends('layouts.admin')
@section('title', 'Laporan Stok')
@section('page_title', 'Laporan Stok')

@section('content')
@php
    $isMovement = $filters['tab'] === 'movement';
    $number = fn ($value, $decimals = 0) => number_format($value, $decimals, ',', '.');
    $dateLabel = fn ($value) => \Illuminate\Support\Carbon::parse($value)->locale('id')->translatedFormat('j M Y');
    $period = $dateLabel($filters['date_from']).' – '.$dateLabel($filters['date_to']);
@endphp
<style>
    .stock-report { color: #172b4d; font-size: 14px; }
    .stock-report .sr-tabs { display: flex; gap: 24px; border-bottom: 1px solid #dce4ed; margin-bottom: 22px; }
    .stock-report .sr-tab { display: block; padding: 12px 2px 15px; color: #64748b; font-weight: 700; border-bottom: 3px solid transparent; }
    .stock-report .sr-tab.active { color: #166534; border-bottom-color: #15803d; }
    .stock-report .sr-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
    .stock-report .sr-title { font-size: 17px; line-height: 1.5; font-weight: 700; margin: 0 0 5px; }
    .stock-report .sr-muted { color: #64748b; font-size: 13px; line-height: 1.65; margin: 0; }
    .stock-report .sr-filter { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 22px; }
    .stock-report .sr-field { min-width: 0; }
    .stock-report .sr-field label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 7px; }
    .stock-report .sr-input { width: 100%; min-width: 0; height: 43px; border: 1px solid #dce4ed; border-radius: 7px; background: #f8fafc; color: #334155; padding: 9px 11px; font: inherit; }
    .stock-report .sr-actions { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-top: 20px; }
    .stock-report .sr-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
    .stock-report .sr-button { display: inline-flex; align-items: center; justify-content: center; padding: 10px 17px; min-height: 42px; border: 1px solid #dce4ed; border-radius: 7px; background: #fff; color: #334155; font: inherit; font-weight: 600; cursor: pointer; }
    .stock-report .sr-primary { color: #fff; background: #15803d; border-color: #15803d; }
    .stock-report .sr-primary:hover { background: #166534; }
    .stock-report .sr-export { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
    .stock-report :is(a, button, input, select):focus-visible { outline: 3px solid #93c5fd; outline-offset: 3px; }
    .stock-report .sr-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .stock-report .sr-stat { min-width: 0; border: 1px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 3px 10px #0f172a06; }
    .stock-report .sr-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 7px; }
    .stock-report .sr-value { font-size: 29px; font-weight: 800; line-height: 1.3; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .stock-report .sr-meta { font-size: 12px; color: #64748b; line-height: 1.5; margin-top: 5px; }
    .stock-report .sr-blue { color: #0369a1; }
    .stock-report .sr-green { color: #15803d; }
    .stock-report .sr-red { color: #be185d; }
    .stock-report .sr-amber { color: #a16207; }
    .stock-report .sr-table-header { display: flex; align-items: start; justify-content: space-between; gap: 18px; margin-bottom: 18px; flex-wrap: wrap; }
    .stock-report .sr-scroll { overflow-x: auto; }
    .stock-report .sr-scroll-hint { display: none; color: #64748b; font-size: 12px; margin: 0 0 12px; }
    .stock-report .sr-table { width: 100%; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
    .stock-report .sr-table th { background: #f8fafc; color: #526077; font-size: 11px; text-transform: uppercase; letter-spacing: .02em; font-weight: 700; white-space: nowrap; }
    .stock-report .sr-table th, .stock-report .sr-table td { padding: 14px 12px; border-bottom: 1px solid #edf1f5; text-align: left; vertical-align: middle; }
    .stock-report .sr-table .sr-num { text-align: right; white-space: nowrap; }
    .stock-report .sr-table tbody tr:hover { background: #fafcfe; }
    .stock-report .sr-product { min-width: 210px; max-width: 360px; overflow-wrap: anywhere; }
    .stock-report .sr-sku { display: block; font-weight: 700; color: #172b4d; margin-bottom: 4px; }
    .stock-report .sr-sub { display: block; color: #64748b; font-size: 12px; margin-top: 4px; }
    .stock-report .sr-badge { display: inline-block; border-radius: 6px; padding: 6px 9px; font-size: 11px; font-weight: 700; white-space: nowrap; background: #f1f5f9; color: #475569; }
    .stock-report .sr-badge-fast { background: #ecfdf5; color: #166534; }
    .stock-report .sr-badge-slow { background: #fffbeb; color: #92400e; }
    .stock-report .sr-note { margin-top: 16px; padding: 14px 16px; border-radius: 8px; background: #f8fafc; color: #526077; font-size: 12px; line-height: 1.7; }
    .stock-report .sr-pagination { margin-top: 20px; display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    @media (max-width: 1100px) { .stock-report .sr-filter, .stock-report .sr-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .stock-report .sr-scroll-hint { display: block; } }
    @media (max-width: 575px) {
        .stock-report .sr-panel { padding: 18px 14px; }
        .stock-report .sr-filter { gap: 14px 10px; }
        .stock-report .sr-stats { gap: 10px; }
        .stock-report .sr-stat { padding: 15px 12px; }
        .stock-report .sr-value { font-size: 25px; }
        .stock-report .sr-tabs { gap: 18px; }
        .stock-report .sr-tab { font-size: 13px; }
        .stock-report .sr-input { font-size: 13px; padding: 8px; }
    }
</style>
<div class="stock-report">
    <nav class="sr-tabs" aria-label="Tab laporan stok">
        @foreach (['balance' => 'Saldo Stok', 'movement' => 'Pergerakan Stok'] as $tab => $label)
            <a class="sr-tab {{ $filters['tab'] === $tab ? 'active' : '' }}" href="{{ route('admin.reports.stock-as-of.index', array_merge($filters, ['tab' => $tab, 'movement' => ''])) }}" @if ($filters['tab'] === $tab) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    <section class="sr-panel">
        <h2 class="sr-title">{{ $isMovement ? 'Analisis Pergerakan Stok' : 'Saldo Stok per Periode' }}</h2>
        <p class="sr-muted">{{ $isMovement ? 'Kenali barang yang sering keluar, bergerak lambat, dan tidak memiliki mutasi keluar.' : 'Lihat stok awal, barang masuk, barang keluar, dan stok akhir dalam satu periode.' }}</p>
        <form method="GET" action="{{ route('admin.reports.stock-as-of.index') }}">
            <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
            <div class="sr-filter">
                <div class="sr-field"><label for="date_from">Tanggal Awal</label><input class="sr-input" id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from']) }}" required></div>
                <div class="sr-field"><label for="date_to">Tanggal Akhir</label><input class="sr-input" id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to']) }}" required></div>
                <div class="sr-field"><label for="stock_type">Jenis Stok</label><select class="sr-input" id="stock_type" name="stock_type"><option value="regular" @selected($filters['stock_type'] === 'regular')>Stok reguler</option><option value="damaged" @selected($filters['stock_type'] === 'damaged')>Stok rusak</option></select></div>
                <div class="sr-field"><label for="category_id">Kategori</label><select class="sr-input" id="category_id" name="category_id"><option value="">Semua kategori</option><option value="0" @selected((string) $filters['category_id'] === '0')>Tanpa kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div class="sr-field"><label for="q">Cari Barang</label><input class="sr-input" id="q" name="q" value="{{ $filters['q'] }}" placeholder="SKU, nama, atau alamat" maxlength="200"></div>
                <div class="sr-field"><label for="status">Status Stok Akhir</label><select class="sr-input" id="status" name="status">@foreach (['' => 'Semua status', 'positive' => 'Stok positif', 'zero' => 'Stok nol', 'negative' => 'Stok negatif', 'low' => 'Di bawah safety stock'] as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></div>
                @if ($isMovement)
                    <div class="sr-field"><label for="movement">Pergerakan</label><select class="sr-input" id="movement" name="movement"><option value="">Semua pergerakan</option>@foreach ($movements as $value => $label)<option value="{{ $value }}" @selected($filters['movement'] === $value)>{{ $label }}</option>@endforeach</select></div>
                @endif
                <div class="sr-field"><label for="per_page">Baris per Halaman</label><select class="sr-input" id="per_page" name="per_page">@foreach ([10, 25, 50, 100] as $count)<option value="{{ $count }}" @selected($filters['per_page'] === $count)>{{ $count }} baris</option>@endforeach</select></div>
            </div>
            <div class="sr-actions">
                <span class="sr-muted">{{ $period }} · {{ $days }} hari · Stok {{ $filters['stock_type'] === 'damaged' ? 'rusak' : 'reguler' }}</span>
                <div class="sr-buttons"><a class="sr-button" href="{{ route('admin.reports.stock-as-of.index', ['tab' => $filters['tab']]) }}">Reset</a><button class="sr-button sr-primary" type="submit">Tampilkan</button></div>
            </div>
        </form>
    </section>

    <div class="sr-stats">
        @php
            $cards = $isMovement
                ? [['SKU Dianalisis', $summary->total_sku, 'Sesuai filter yang diterapkan', 'blue'], ['Fast Moving', $summary->fast, 'Keluar pada ≥ 50% hari periode', 'green'], ['Slow Moving', $summary->slow, 'Keluar pada < 50% hari periode', 'amber'], ['Non-moving', $summary->non, 'Tidak ada mutasi keluar', 'red']]
                : [['Stok Awal', $summary->opening, 'Sebelum '.$dateLabel($filters['date_from']), 'blue'], ['Qty In', $summary->qty_in, 'Total masuk selama periode', 'green'], ['Qty Out', $summary->qty_out, 'Total keluar selama periode', 'red'], ['Stok Akhir', $summary->closing, 'Sampai '.$dateLabel($filters['date_to']), 'blue']];
        @endphp
        @foreach ($cards as [$label, $value, $meta, $color])
            <div class="sr-stat"><div class="sr-label">{{ $label }}</div><div class="sr-value sr-{{ $color }}">{{ $number($value) }}</div><div class="sr-meta">{{ $meta }}</div></div>
        @endforeach
    </div>

    <section class="sr-panel">
        <div class="sr-table-header">
            <div><h2 class="sr-title">{{ $isMovement ? 'Rincian Pergerakan Barang' : 'Rincian Saldo Stok' }}</h2><p class="sr-muted">{{ $number($summary->total_sku) }} SKU sesuai filter. {{ $isMovement ? 'Diurutkan dari qty keluar terbesar.' : 'Stok akhir = stok awal + qty in − qty out.' }}</p></div>
            <a class="sr-button sr-export" href="{{ route('admin.reports.stock-as-of.export', $filters) }}">Unduh Excel</a>
        </div>
        <p class="sr-scroll-hint">Geser tabel ke samping untuk melihat seluruh kolom →</p>
        <div class="sr-scroll" role="region" aria-label="Tabel laporan stok, geser horizontal untuk melihat seluruh kolom" tabindex="0">
            <table class="sr-table">
                <thead><tr><th scope="col">Barang</th><th scope="col">Kategori / Satuan</th>
                    @if ($isMovement)
                        <th scope="col" class="sr-num">Qty Out</th><th scope="col" class="sr-num">Rata-rata / Hari</th><th scope="col" class="sr-num">Hari Keluar</th><th scope="col">Pergerakan</th><th scope="col" class="sr-num">Stok Akhir</th><th scope="col">Keluar Terakhir</th>
                    @else
                        <th scope="col" class="sr-num">Stok Awal</th><th scope="col" class="sr-num">Qty In</th><th scope="col" class="sr-num">Qty Out</th><th scope="col" class="sr-num">Stok Akhir</th>
                    @endif
                </tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="sr-product"><span class="sr-sku">{{ $row->sku }}</span>{{ $row->name }}<span class="sr-sub">Alamat: {{ $row->address ?: '—' }}</span></td>
                            <td>{{ $row->category }}<span class="sr-sub">{{ $row->uom ?: 'Satuan belum diatur' }}</span></td>
                            @if ($isMovement)
                                <td class="sr-num"><strong>{{ $number($row->qty_out) }}</strong></td><td class="sr-num">{{ $number($row->average_out, 2) }}</td><td class="sr-num">{{ $row->outgoing_days }} / {{ $days }}</td>
                                <td><span class="sr-badge sr-badge-{{ $row->movement }}">{{ $movements[$row->movement] }}</span></td>
                                <td class="sr-num {{ $row->closing < 0 ? 'sr-red' : '' }}"><strong>{{ $number($row->closing) }}</strong></td>
                                <td>{{ $row->last_out_at ? $dateLabel($row->last_out_at) : 'Belum ada' }}</td>
                            @else
                                <td class="sr-num">{{ $number($row->opening) }}</td><td class="sr-num sr-green">{{ $number($row->qty_in) }}</td><td class="sr-num sr-red">{{ $number($row->qty_out) }}</td><td class="sr-num {{ $row->closing < 0 ? 'sr-red' : '' }}"><strong>{{ $number($row->closing) }}</strong></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isMovement ? 8 : 6 }}" style="text-align: center; padding: 40px 16px; color: #64748b;">Tidak ada barang yang sesuai filter. Coba ubah pencarian, kategori, atau status.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sr-pagination"><span class="sr-muted">Menampilkan {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} dari {{ $number($rows->total()) }} SKU</span><div class="sr-buttons">@if ($rows->previousPageUrl())<a class="sr-button" href="{{ $rows->previousPageUrl() }}">Sebelumnya</a>@endif @if ($rows->hasMorePages())<a class="sr-button" href="{{ $rows->nextPageUrl() }}">Berikutnya</a>@endif</div></div>
        <div class="sr-note">
            @if ($isMovement)
                <strong>Kriteria pergerakan:</strong> fast moving memiliki mutasi keluar pada minimal {{ (int) ceil($days / 2) }} dari {{ $days }} hari; slow moving memiliki mutasi keluar tetapi di bawah batas tersebut; non-moving tidak memiliki mutasi keluar selama periode.<br>
                Rata-rata = qty out ÷ {{ $days }} hari kalender. Seluruh jenis mutasi keluar dihitung, termasuk penyesuaian dan retur; ini bukan laporan penjualan. Keluar terakhir dihitung sampai tanggal akhir, termasuk sebelum periode.<br>
            @endif
            Hanya SKU fisik aktif; bundle virtual tidak dijumlahkan agar komponennya tidak terhitung ganda. Saldo berasal dari mutasi yang tercatat, bukan stok saat ini. Total qty mengikuti satuan masing-masing barang. Rentang maksimal 366 hari.
        </div>
    </section>
</div>
@endsection
