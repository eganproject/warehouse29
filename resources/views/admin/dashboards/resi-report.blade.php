@php
    $summary = $report['summary'];
    $formatDate = fn ($date) => \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('j M Y');
    $period = $formatDate($reportFilters['report_start']).' – '.$formatDate($reportFilters['report_end']);
@endphp
<style>
    .resi-report { color: #172b4d; font-size: 14px; }
    .resi-report .rr-panel { background: #fff; border: 1px solid #e3e9f0; border-radius: 10px; padding: 26px 28px; }
    .resi-report .rr-header { display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; margin-bottom: 20px; }
    .resi-report .rr-title { font-size: 16px; font-weight: 700; line-height: 1.5; margin: 0 0 4px; color: #172b4d; }
    .resi-report .rr-description { color: #64748b; font-size: 13px; line-height: 1.6; margin: 0; }
    .resi-report .rr-filter { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 12px; }
    .resi-report .rr-field { display: flex; flex-direction: column; gap: 6px; }
    .resi-report .rr-field label { font-size: 12px; font-weight: 600; margin: 0; }
    .resi-report .rr-field input { height: 44px; width: 160px; max-width: 100%; min-width: 0; border: 1px solid #dce4ed; border-radius: 7px; background: #f8fafc; color: #334155; padding: 10px 12px; font: inherit; }
    .resi-report .rr-submit { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 10px 20px; border: 1px solid #15803d; border-radius: 7px; background: #15803d; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
    .resi-report .rr-submit:hover { background: #166534; border-color: #166534; }
    .resi-report .rr-field input:focus-visible, .resi-report .rr-submit:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
    .resi-report .rr-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .resi-report .rr-stat { min-width: 0; padding: 20px; background: #fff; border: 1px solid #e3e9f0; border-radius: 12px; box-shadow: 0 3px 12px rgba(15, 23, 42, .04); }
    .resi-report .rr-label { font-size: 11px; font-weight: 800; letter-spacing: .045em; text-transform: uppercase; color: #59677c; margin-bottom: 6px; }
    .resi-report .rr-value { font-size: 30px; font-weight: 800; line-height: 1.2; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .resi-report .rr-meta { font-size: 12px; color: #64748b; line-height: 1.5; margin-top: 5px; }
    .resi-report .rr-green { color: #15803d; }
    .resi-report .rr-blue { color: #0277bd; }
    .resi-report .rr-amber { color: #a16207; }
    .resi-report .rr-red { color: #be185d; }
    .resi-report .rr-table-wrap { overflow-x: auto; margin-top: 22px; }
    .resi-report .rr-table { width: 100%; border-collapse: collapse; font-size: 14px; font-variant-numeric: tabular-nums; }
    .resi-report .rr-table th { color: #64748b; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .025em; padding: 12px 16px; text-align: right; }
    .resi-report .rr-table td { padding: 13px 16px; border-top: 1px dashed #e5eaf0; text-align: right; }
    .resi-report .rr-table th:first-child, .resi-report .rr-table td:first-child { text-align: left; padding-left: 0; }
    .resi-report .rr-table th:last-child, .resi-report .rr-table td:last-child { padding-right: 0; }
    .resi-report .rr-table tbody tr:hover { background: #f8fafc; }
    .resi-report .rr-table td.rr-green, .resi-report .rr-table td.rr-red { font-weight: 600; }
    .resi-report .rr-note { color: #64748b; font-size: 12px; line-height: 1.6; margin: 16px 0 0; }
    .resi-report .rr-empty { margin: 18px 0 0; padding: 12px 16px; border-radius: 7px; background: #f1f5f9; color: #475569; }
    @media (max-width: 1100px) {
        .resi-report .rr-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575px) {
        .resi-report .rr-panel { padding: 20px 16px; }
        .resi-report .rr-header { gap: 18px; }
        .resi-report .rr-filter { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
        .resi-report .rr-field input { width: 100%; font-size: 13px; padding: 8px; }
        .resi-report .rr-submit { grid-column: 1 / -1; }
        .resi-report .rr-stats { gap: 10px; }
        .resi-report .rr-stat { padding: 16px 12px; }
        .resi-report .rr-value { font-size: 26px; }
        .resi-report .rr-table { font-size: 13px; }
        .resi-report .rr-table th, .resi-report .rr-table td { padding: 12px 8px; }
    }
</style>
<div class="resi-report">
    <div class="rr-panel rr-header">
        <div>
            <h2 class="rr-title">Rata-rata Resi per Hari</h2>
            <p class="rr-description">Jumlah resi aktif dihitung per hari kalender, termasuk hari tanpa resi.</p>
        </div>
        <form method="GET" action="{{ url()->current() }}" class="rr-filter">
            <input type="hidden" name="tab" value="report">
            <input type="hidden" name="date" value="{{ $today }}">
            <div class="rr-field">
                <label for="report_start">Tanggal Awal</label>
                <input type="date" id="report_start" name="report_start" value="{{ old('report_start', $reportFilters['report_start']) }}" required>
            </div>
            <div class="rr-field">
                <label for="report_end">Tanggal Akhir</label>
                <input type="date" id="report_end" name="report_end" value="{{ old('report_end', $reportFilters['report_end']) }}" required>
            </div>
            <button class="rr-submit" type="submit"><i class="fa-solid fa-chart-column" aria-hidden="true" style="color: inherit;"></i>Tampilkan</button>
        </form>
    </div>

    <div class="rr-stats">
        <div class="rr-stat">
            <div class="rr-label">Total Resi Aktif</div>
            <div class="rr-value rr-green">{{ number_format($summary->active, 0, ',', '.') }}</div>
            <div class="rr-meta">{{ $period }} · {{ $summary->days }} hari</div>
        </div>
        <div class="rr-stat">
            <div class="rr-label">Rata-rata Resi / Hari</div>
            <div class="rr-value rr-blue">{{ number_format($summary->average, 2, ',', '.') }}</div>
            <div class="rr-meta">Berdasarkan seluruh hari dalam periode.</div>
        </div>
        <div class="rr-stat">
            <div class="rr-label">Jumlah Tertinggi</div>
            <div class="rr-value rr-amber">{{ number_format($summary->highest, 0, ',', '.') }}</div>
            <div class="rr-meta">
                @if ($summary->peak_date)
                    Pada {{ $formatDate($summary->peak_date) }}
                    @if ($summary->peak_days > 1)
                        · dan {{ $summary->peak_days - 1 }} hari lainnya
                    @endif
                @else
                    Belum ada resi aktif.
                @endif
            </div>
        </div>
        <div class="rr-stat">
            <div class="rr-label">Resi Dibatalkan</div>
            <div class="rr-value rr-red">{{ number_format($summary->canceled, 0, ',', '.') }}</div>
            <div class="rr-meta">Tidak termasuk resi aktif.</div>
        </div>
    </div>

    <section class="rr-panel" aria-labelledby="rr-daily-title">
        <h2 class="rr-title" id="rr-daily-title">Rincian Resi Harian</h2>
        <p class="rr-description">Gunakan periode di atas untuk melihat tren jumlah resi.</p>
        @if ($summary->active + $summary->canceled === 0)
            <p class="rr-empty" role="status">Tidak ada resi pada periode ini. Silakan pilih periode lain.</p>
        @endif
        <div class="rr-table-wrap">
            <table class="rr-table" aria-labelledby="rr-daily-title">
                <thead><tr><th scope="col">Tanggal</th><th scope="col">Resi Aktif</th><th scope="col">Resi Dibatalkan</th></tr></thead>
                <tbody>
                    @foreach ($report['daily'] as $row)
                        <tr>
                            <td>{{ $formatDate($row->date) }}</td>
                            <td class="rr-green">{{ number_format($row->active, 0, ',', '.') }}</td>
                            <td class="rr-red">{{ number_format($row->canceled, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="rr-note">Berdasarkan tanggal upload dan status terbaru resi. Rentang maksimal 366 hari.</p>
    </section>
</div>
