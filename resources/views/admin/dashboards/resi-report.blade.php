@php
    $summary = $report['summary'];
    $active = (int) $summary->total - (int) $summary->canceled;
    $remaining = $active - (int) $summary->scanned;
    $rate = $active > 0 ? number_format($summary->scanned / $active * 100, 1).'%' : '—';
@endphp
<div class="dash-panel mb-6">
    <div class="dash-section-title mb-2"><i class="fa-solid fa-chart-column"></i> Laporan Resi</div>
    <p class="text-muted">Analisis berdasarkan <strong>tanggal upload</strong> {{ $reportFilters['report_start'] }} s.d. {{ $reportFilters['report_end'] }}.
        Status adalah kondisi terbaru saat halaman dibuka, bukan posisi historis pada akhir periode. Scan out berarti keluar gudang, bukan konfirmasi diterima pembeli.</p>
    <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
        <input type="hidden" name="tab" value="report">
        <input type="hidden" name="date" value="{{ $today }}">
        <div class="col-sm-6 col-lg-2">
            <label class="form-label" for="report_start">Tanggal mulai</label>
            <input class="form-control" type="date" id="report_start" name="report_start" value="{{ old('report_start', $reportFilters['report_start']) }}" required>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label class="form-label" for="report_end">Tanggal akhir</label>
            <input class="form-control" type="date" id="report_end" name="report_end" value="{{ old('report_end', $reportFilters['report_end']) }}" required>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="report_kurir">Kurir</label>
            <select class="form-select" id="report_kurir" name="report_kurir">
                <option value="">Semua kurir (termasuk tanpa kurir)</option>
                @foreach ($kurirs as $courier)
                    <option value="{{ $courier['id'] }}" @selected(($reportFilters['report_kurir'] ?? '') == $courier['id'])>{{ $courier['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="report_status">Status detail resi</label>
            <select class="form-select" id="report_status" name="report_status">
                <option value="">Semua status</option>
                @foreach ($report['stages'] as $key => $label)
                    <option value="{{ $key }}" @selected(($reportFilters['report_status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Terapkan</button>
            <a class="btn btn-light" href="{{ url()->current() }}?tab=report">Reset</a>
        </div>
    </form>
    <div class="dash-legend">Maksimal 366 hari. Filter status hanya membatasi tabel detail; ringkasan dan rekap tetap mencakup seluruh status pada tanggal dan kurir terpilih.</div>
</div>

<div class="stats-grid mb-6">
    @foreach ([
        ['Total resi', number_format($summary->total), number_format($summary->total_qty).' unit termasuk batal', 'blue'],
        ['Resi aktif', number_format($active), number_format($summary->active_qty).' unit aktif', 'blue'],
        ['Sudah scan out', number_format($summary->scanned), $rate.' dari resi aktif', 'green'],
        ['Belum scan out', number_format($remaining), 'Resi aktif yang perlu ditindaklanjuti', 'amber'],
        ['Dibatalkan', number_format($summary->canceled), $summary->total > 0 ? number_format($summary->canceled / $summary->total * 100, 1).'% dari total resi' : 'Belum ada data', 'red'],
    ] as [$label, $value, $meta, $color])
        <div class="stat-card stat-card--{{ $color }}">
            <div class="stat-body"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div><div class="stat-meta">{{ $meta }}</div></div>
        </div>
    @endforeach
</div>

@if ($summary->total == 0)
    <div class="alert alert-info">Tidak ada resi pada periode dan kurir terpilih. Coba ubah filter tanggal atau kurir.</div>
@else
    <div class="dash-panel mb-6">
        <div class="dash-section-title mb-3">Prioritas tindak lanjut</div>
        <div class="row g-4">
            <div class="col-md-4"><strong>{{ number_format($summary->pending) }} belum QC</strong><div class="text-muted">Periksa kesiapan picking dan antrean QC.</div></div>
            <div class="col-md-4"><strong>{{ number_format($summary->qc_progress) }} QC berlangsung</strong><div class="text-muted">Periksa kelengkapan barang yang belum selesai diperiksa.</div></div>
            <div class="col-md-4"><strong>{{ number_format($summary->ready) }} QC selesai, belum scan out</strong><div class="text-muted">Prioritaskan penyelesaian packing dan serah terima kurir.</div></div>
        </div>
    </div>
@endif

@foreach (['couriers' => 'Rekap per Kurir', 'daily' => 'Tren Harian'] as $group => $heading)
    <div class="dash-table-card mb-6">
        <div class="dash-table-head"><div><div class="dash-table-title">{{ $heading }}</div>
            <div class="dash-table-sub">{{ $group === 'couriers' ? 'Urutan berdasarkan jumlah resi belum scan out terbesar.' : 'Jumlah resi menurut tanggal upload, termasuk hari tanpa aktivitas.' }} Penyelesaian = scan out ÷ resi aktif.</div>
        </div></div>
        <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
            <table class="table dash-mini-table table-row-dashed align-middle">
                <thead><tr><th>{{ $group === 'couriers' ? 'Kurir' : 'Tanggal upload' }}</th><th>Total</th><th>Aktif</th><th>Belum QC</th><th>QC berlangsung</th><th>QC selesai, belum keluar</th><th>Scan out</th><th>Batal</th><th>Unit aktif</th><th>Penyelesaian</th></tr></thead>
                <tbody>
                    @forelse ($report[$group] as $row)
                        @php
                            $rowActive = $row->total - $row->canceled;
                            $rowRate = $rowActive > 0 ? round($row->scanned / $rowActive * 100, 1) : null;
                        @endphp
                        <tr>
                            <td class="fw-bold text-nowrap">{{ $group === 'couriers' ? $row->courier_name : $row->tanggal_upload }}</td>
                            @foreach ([$row->total, $rowActive, $row->pending, $row->qc_progress, $row->ready, $row->scanned, $row->canceled, $row->active_qty] as $value)
                                <td>{{ number_format($value) }}</td>
                            @endforeach
                            <td style="min-width: 110px;">
                                {{ $rowRate === null ? '—' : number_format($rowRate, 1).'%' }}
                                @if ($rowRate !== null)
                                    <div class="progress mt-1" style="height: 5px;" aria-hidden="true"><div class="progress-bar bg-success" style="width: {{ $rowRate }}%"></div></div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-5">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach

<div class="dash-table-card mb-6">
    <div class="dash-table-head"><div><div class="dash-table-title">Detail Resi · {{ number_format($report['details']->total()) }} data</div>
        <div class="dash-table-sub">Prioritas: belum QC, QC berlangsung, menunggu scan out, sudah scan out, lalu batal. Dalam setiap tahap, tanggal upload terlama ditampilkan lebih dahulu.</div>
    </div></div>
    <div class="table-responsive">
        <table class="table dash-mini-table table-row-dashed align-middle">
            <thead><tr><th>Pesanan / Resi</th><th>Tanggal upload</th><th>Kurir</th><th>Unit</th><th>Status terbaru</th><th>QC selesai</th><th>Scan out</th></tr></thead>
            <tbody>
                @forelse ($report['details'] as $row)
                    <tr>
                        <td><div class="fw-bold">{{ $row->no_resi ?: 'Nomor resi belum tersedia' }}</div><div class="text-muted">{{ $row->id_pesanan }}</div></td>
                        <td class="text-nowrap">{{ $row->tanggal_upload }}</td><td>{{ $row->courier_name }}</td><td>{{ number_format($row->total_qty) }}</td>
                        <td><span class="badge {{ $row->stage === 'canceled' ? 'badge-light-danger' : ($row->stage === 'scanned' ? 'badge-light-success' : 'badge-light-warning') }}">{{ $report['stages'][$row->stage] }}</span>
                            @if ($row->stage === 'canceled' && $row->cancel_reason)<div class="text-muted mt-1">{{ $row->cancel_reason }}</div>@endif
                        </td>
                        <td class="text-nowrap">{{ $row->completed_at ? \Illuminate\Support\Carbon::parse($row->completed_at)->format('d/m/Y H:i') : '—' }}</td>
                        <td class="text-nowrap">{{ $row->scanned_at ? \Illuminate\Support\Carbon::parse($row->scanned_at)->format('d/m/Y H:i') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">Tidak ada resi yang sesuai dengan filter detail.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <span class="text-muted">Menampilkan {{ $report['details']->firstItem() ?? 0 }}–{{ $report['details']->lastItem() ?? 0 }} dari {{ number_format($report['details']->total()) }} resi.</span>
        <div class="d-flex gap-2">
            @if ($report['details']->previousPageUrl())<a class="btn btn-sm btn-light" href="{{ $report['details']->previousPageUrl() }}">Sebelumnya</a>@endif
            @if ($report['details']->nextPageUrl())<a class="btn btn-sm btn-light-primary" href="{{ $report['details']->nextPageUrl() }}">Berikutnya</a>@endif
        </div>
    </div>
</div>
