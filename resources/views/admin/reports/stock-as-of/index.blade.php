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
    .stock-report .sr-stats-movement { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .stock-report .sr-stat { min-width: 0; border: 1px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 3px 10px #0f172a06; }
    .stock-report .sr-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 7px; }
    .stock-report .sr-value { font-size: 29px; font-weight: 800; line-height: 1.3; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .stock-report .sr-meta { font-size: 12px; color: #64748b; line-height: 1.5; margin-top: 5px; }
    .stock-report .sr-blue { color: #0369a1; }
    .stock-report .sr-green { color: #15803d; }
    .stock-report .sr-orange { color: #c2410c; }
    .stock-report .sr-red { color: #be185d; }
    .stock-report .sr-amber { color: #a16207; }
    .stock-report .sr-chart-panel { padding-bottom: 18px; }
    .stock-report .sr-chart-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 8px; }
    .stock-report .sr-chart-legend { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; color: #526077; font-size: 12px; font-weight: 600; }
    .stock-report .sr-chart-legend span { display: inline-flex; align-items: center; gap: 7px; }
    .stock-report .sr-chart-legend i { display: inline-block; width: 22px; height: 3px; border-radius: 999px; background: var(--legend-color); }
    .stock-report .sr-chart { min-height: 340px; }
    .stock-report .sr-chart-fallback { display: grid; place-items: center; min-height: 300px; color: #64748b; font-size: 13px; }
    .stock-report .sr-table-header { display: flex; align-items: start; justify-content: space-between; gap: 18px; margin-bottom: 18px; flex-wrap: wrap; }
    .stock-report .sr-scroll { overflow-x: auto; border: 1px solid #e7edf3; border-radius: 9px; }
    .stock-report .sr-scroll-hint { display: none; color: #64748b; font-size: 12px; margin: 0 0 12px; }
    .stock-report .sr-table { width: 100%; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
    .stock-report .sr-table th { position: sticky; top: 0; z-index: 1; background: #f8fafc; color: #526077; font-size: 11px; text-transform: uppercase; letter-spacing: .02em; font-weight: 700; white-space: nowrap; }
    .stock-report .sr-table th, .stock-report .sr-table td { padding: 14px 12px; border-bottom: 1px solid #edf1f5; text-align: left; vertical-align: middle; }
    .stock-report .sr-table .sr-num { text-align: right; white-space: nowrap; }
    .stock-report .sr-table tbody tr:hover { background: #fafcfe; }
    .stock-report .sr-table-movement { min-width: 1080px; }
    .stock-report .sr-index { width: 54px; text-align: center !important; color: #64748b; white-space: nowrap; }
    .stock-report .sr-product { min-width: 210px; max-width: 360px; overflow-wrap: anywhere; }
    .stock-report .sr-sku { display: block; font-weight: 700; color: #172b4d; margin-bottom: 4px; }
    .stock-report .sr-sub { display: block; color: #64748b; font-size: 12px; margin-top: 4px; }
    .stock-report .sr-badge { display: inline-block; border-radius: 6px; padding: 6px 9px; font-size: 11px; font-weight: 700; white-space: nowrap; background: #f1f5f9; color: #475569; }
    .stock-report .sr-badge-fast { background: #ecfdf5; color: #166534; }
    .stock-report .sr-badge-medium { background: #fff7ed; color: #9a3412; }
    .stock-report .sr-badge-slow { background: #fffbeb; color: #92400e; }
    .stock-report .sr-badge-non { background: #fef2f2; color: #b91c1c; }
    .stock-report .sr-metric { display: block; font-weight: 700; color: #27364d; }
    .stock-report .sr-note { margin-top: 16px; padding: 14px 16px; border-radius: 8px; background: #f8fafc; color: #526077; font-size: 12px; line-height: 1.7; }
    .stock-report .sr-pagination { margin-top: 20px; display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    @media (max-width: 1200px) { .stock-report .sr-stats-movement { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 1100px) { .stock-report .sr-filter, .stock-report .sr-stats, .stock-report .sr-stats-movement { grid-template-columns: repeat(2, minmax(0, 1fr)); } .stock-report .sr-scroll-hint { display: block; } }
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
                <div class="sr-field"><label for="q">Cari SKU</label><input class="sr-input" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Masukkan SKU lengkap" maxlength="200"></div>
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

    <div class="sr-stats {{ $isMovement ? 'sr-stats-movement' : '' }}">
        @php
            $cards = $isMovement
                ? [['SKU Dianalisis', $summary->total_sku, 'Sesuai filter yang diterapkan', 'blue'], ['Fast Moving', $summary->fast, 'Kontribusi kumulatif hingga 70%', 'green'], ['Medium Moving', $summary->medium, 'Lapisan kontribusi 70%–90%', 'orange'], ['Slow Moving', $summary->slow, 'Sisa kontribusi setelah 90%', 'amber'], ['Non Moving', $summary->non, 'Tidak ada barang keluar', 'red']]
                : [['Stok Awal', $summary->opening, 'Sebelum '.$dateLabel($filters['date_from']), 'blue'], ['Qty In', $summary->qty_in, 'Total masuk selama periode', 'green'], ['Qty Out', $summary->qty_out, 'Total keluar selama periode', 'red'], ['Stok Akhir', $summary->closing, 'Sampai '.$dateLabel($filters['date_to']), 'blue']];
        @endphp
        @foreach ($cards as [$label, $value, $meta, $color])
            <div class="sr-stat"><div class="sr-label">{{ $label }}</div><div class="sr-value sr-{{ $color }}">{{ $number($value) }}</div><div class="sr-meta">{{ $meta }}</div></div>
        @endforeach
    </div>

    @if ($isMovement)
        <section class="sr-panel sr-chart-panel" aria-labelledby="stock-movement-chart-title">
            <div class="sr-chart-header">
                <div>
                    <h2 class="sr-title" id="stock-movement-chart-title">Tren Pergerakan Stok</h2>
                    <p class="sr-muted">Total mutasi masuk dan keluar per tanggal · {{ $period }}</p>
                </div>
                <div class="sr-chart-legend" aria-label="Keterangan grafik">
                    <span><i style="--legend-color: #0f9f6e"></i>Qty Masuk</span>
                    <span><i style="--legend-color: #e05263"></i>Qty Keluar</span>
                </div>
            </div>
            <div id="stock-movement-chart" class="sr-chart" role="img" aria-label="Grafik garis jumlah stok masuk dan keluar berdasarkan tanggal"></div>
        </section>
    @endif

    <section class="sr-panel">
        <div class="sr-table-header">
            <div><h2 class="sr-title">{{ $isMovement ? 'Rincian Pergerakan Barang' : 'Rincian Saldo Stok' }}</h2><p class="sr-muted">{{ $number($summary->total_sku) }} SKU sesuai filter. {{ $isMovement ? 'Diurutkan dari qty keluar terbesar.' : 'Stok akhir = stok awal + qty in − qty out.' }}</p></div>
            <a class="sr-button sr-export" href="{{ route('admin.reports.stock-as-of.export', $filters) }}">Unduh Excel</a>
        </div>
        <p class="sr-scroll-hint">Geser tabel ke samping untuk melihat seluruh kolom →</p>
        <div class="sr-scroll" role="region" aria-label="Tabel laporan stok, geser horizontal untuk melihat seluruh kolom" tabindex="0">
            <table class="sr-table {{ $isMovement ? 'sr-table-movement' : '' }}">
                <thead><tr>
                    @if ($isMovement)
                        <th scope="col" class="sr-index">No</th><th scope="col">SKU / Item</th><th scope="col">Klasifikasi</th><th scope="col" class="sr-num">Qty Keluar</th><th scope="col" class="sr-num">Rata-rata / Hari</th><th scope="col" class="sr-num">Kontribusi</th><th scope="col" class="sr-num">Frequency</th><th scope="col" class="sr-num">Days Cover</th><th scope="col">Tanggal Terakhir Keluar</th>
                    @else
                        <th scope="col">Barang</th><th scope="col">Kategori / Satuan</th>
                        <th scope="col" class="sr-num">Stok Awal</th><th scope="col" class="sr-num">Qty In</th><th scope="col" class="sr-num">Qty Out</th><th scope="col" class="sr-num">Stok Akhir</th>
                    @endif
                </tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @if ($isMovement)
                                <td class="sr-index">{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="sr-product"><span class="sr-sku">{{ $row->sku }}</span>{{ $row->name }}<span class="sr-sub">{{ $row->category }}{{ $row->uom ? ' · '.$row->uom : '' }}</span></td>
                                <td><span class="sr-badge sr-badge-{{ $row->movement }}">{{ $movements[$row->movement] }}</span></td>
                                <td class="sr-num"><span class="sr-metric">{{ $number($row->qty_out) }}</span></td>
                                <td class="sr-num">{{ $number($row->average_out, 2) }}</td>
                                <td class="sr-num"><span class="sr-metric">{{ $number($row->contribution_percent, 2) }}%</span><span class="sr-sub">Kumulatif {{ $number($row->cumulative_percent, 2) }}%</span></td>
                                <td class="sr-num"><span class="sr-metric">{{ $number($row->outgoing_frequency) }} kali</span><span class="sr-sub">{{ $number($row->outgoing_days) }} hari aktif</span></td>
                                <td class="sr-num {{ $row->days_cover !== null && $row->days_cover < 0 ? 'sr-red' : '' }}">{{ $row->days_cover !== null ? $number($row->days_cover, 1).' hari' : '—' }}<span class="sr-sub">Stok: {{ $number($row->closing) }}</span></td>
                                <td>{{ $row->last_out_at ? $dateLabel($row->last_out_at) : 'Belum ada' }}</td>
                            @else
                                <td class="sr-product"><span class="sr-sku">{{ $row->sku }}</span>{{ $row->name }}<span class="sr-sub">Alamat: {{ $row->address ?: '—' }}</span></td>
                                <td>{{ $row->category }}<span class="sr-sub">{{ $row->uom ?: 'Satuan belum diatur' }}</span></td>
                                <td class="sr-num">{{ $number($row->opening) }}</td><td class="sr-num sr-green">{{ $number($row->qty_in) }}</td><td class="sr-num sr-red">{{ $number($row->qty_out) }}</td><td class="sr-num {{ $row->closing < 0 ? 'sr-red' : '' }}"><strong>{{ $number($row->closing) }}</strong></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isMovement ? 9 : 6 }}" style="text-align: center; padding: 40px 16px; color: #64748b;">Tidak ada barang yang sesuai filter. Coba ubah pencarian, kategori, atau status.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sr-pagination"><span class="sr-muted">Menampilkan {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} dari {{ $number($rows->total()) }} SKU</span><div class="sr-buttons">@if ($rows->previousPageUrl())<a class="sr-button" href="{{ $rows->previousPageUrl() }}">Sebelumnya</a>@endif @if ($rows->hasMorePages())<a class="sr-button" href="{{ $rows->nextPageUrl() }}">Berikutnya</a>@endif</div></div>
        <div class="sr-note">
            @if ($isMovement)
                <strong>Kriteria pergerakan:</strong> SKU berqty keluar diurutkan dari terbesar. Fast Moving menyusun lapisan kontribusi awal hingga 70%; Medium Moving menyusun lapisan berikutnya hingga 90%; Slow Moving mengisi sisa kontribusi setelah 90%; Non Moving tidak memiliki barang keluar selama periode.<br>
                Kontribusi dihitung terhadap seluruh qty keluar SKU fisik aktif pada periode. Frequency adalah jumlah kejadian mutasi keluar; hari aktif menunjukkan jumlah tanggal unik terjadinya barang keluar. Rata-rata = qty keluar ÷ {{ $days }} hari kalender. Days Cover = stok akhir ÷ rata-rata per hari. Tanggal terakhir keluar dihitung sampai tanggal akhir, termasuk sebelum periode.<br>
            @endif
            Hanya SKU fisik aktif; bundle virtual tidak dijumlahkan agar komponennya tidak terhitung ganda. Saldo berasal dari mutasi yang tercatat, bukan stok saat ini. Total qty mengikuti satuan masing-masing barang. Rentang maksimal 366 hari.
        </div>
    </section>
</div>
@endsection

@if ($isMovement)
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const element = document.getElementById('stock-movement-chart');
        const trend = @json($dailyTrend);

        if (!element) return;
        if (typeof ApexCharts === 'undefined') {
            element.innerHTML = '<div class="sr-chart-fallback">Grafik tidak dapat dimuat.</div>';
            return;
        }

        const numberText = (value) => Number(value || 0).toLocaleString('id-ID');
        const dateText = (value, options = { day: 'numeric', month: 'short' }) => {
            const parts = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!parts) return '';

            const date = new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]));
            if (Number.isNaN(date.getTime())) return '';

            return new Intl.DateTimeFormat('id-ID', options).format(date);
        };
        const labelCount = trend.dates.length;
        const maxVisibleLabels = window.innerWidth < 576 ? 5 : 9;
        const labelStep = Math.max(1, Math.ceil(labelCount / maxVisibleLabels));

        new ApexCharts(element, {
            series: [
                { name: 'Qty Masuk', data: trend.qty_in },
                { name: 'Qty Keluar', data: trend.qty_out },
            ],
            chart: {
                type: 'line',
                height: 340,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, easing: 'easeinout', speed: 450 },
            },
            colors: ['#0f9f6e', '#e05263'],
            stroke: { curve: 'smooth', width: 3, lineCap: 'round' },
            markers: {
                size: labelCount <= 31 ? 3 : 0,
                hover: { size: 6 },
                strokeWidth: 2,
                strokeColors: '#ffffff',
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            grid: {
                borderColor: '#e8edf3',
                strokeDashArray: 4,
                padding: { left: 12, right: 18, top: 10, bottom: 0 },
                xaxis: { lines: { show: false } },
            },
            xaxis: {
                categories: trend.dates,
                axisBorder: { show: false },
                axisTicks: { show: false },
                title: { text: 'Tanggal', offsetY: 2, style: { color: '#64748b', fontSize: '12px', fontWeight: 600 } },
                labels: {
                    rotate: 0,
                    hideOverlappingLabels: true,
                    formatter: (value) => {
                        const index = trend.dates.indexOf(String(value));
                        if (index < 0 || (index % labelStep !== 0 && index !== labelCount - 1)) return '';
                        return dateText(value);
                    },
                    style: { colors: '#64748b', fontSize: '11px' },
                },
            },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                decimalsInFloat: 0,
                title: { text: 'Jumlah', style: { color: '#64748b', fontSize: '12px', fontWeight: 600 } },
                labels: { formatter: numberText, style: { colors: '#64748b', fontSize: '11px' } },
            },
            tooltip: {
                shared: true,
                intersect: false,
                x: {
                    formatter: (_value, context) => dateText(trend.dates[context.dataPointIndex], {
                        weekday: 'short', day: 'numeric', month: 'long', year: 'numeric'
                    }),
                },
                y: { formatter: (value) => `${numberText(value)} unit` },
                marker: { show: true },
                style: { fontSize: '12px' },
            },
            noData: { text: 'Tidak ada data pergerakan stok pada periode ini.' },
        }).render();
    });
</script>
@endpush
@endif
