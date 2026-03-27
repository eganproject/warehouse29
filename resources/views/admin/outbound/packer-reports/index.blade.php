@extends('layouts.admin')

@section('title', 'Laporan Packer')
@section('page_title', 'Laporan Packer')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari Packer / Tanggal" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $today ?? '' }}">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today ?? '' }}">
                <select class="form-select form-select-solid w-200px" id="filter_packer">
                    <option value="">Semua Packer</option>
                    @foreach($packers as $packer)
                        <option value="{{ $packer->id }}">{{ $packer->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-light" id="filter_apply">Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row g-4 mb-6">
            <div class="col-md-4">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Total Scan</div>
                        <div class="fs-2 fw-bold" id="summary_total_scan">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Total Packer</div>
                        <div class="fs-2 fw-bold" id="summary_total_packer">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Rata-rata Scan / Jam</div>
                        <div class="fs-2 fw-bold" id="summary_avg_hour">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="packer_report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Tanggal</th>
                        <th>Packer</th>
                        <th class="text-end">Total Scan</th>
                        <th class="text-end">Unik Resi</th>
                        <th class="text-end">Avg / Jam</th>
                        <th>Scan Pertama</th>
                        <th>Scan Terakhir</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#packer_report_table');
        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTable unavailable');
            return;
        }

        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const packerSelect = document.getElementById('filter_packer');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const summaryTotalScan = document.getElementById('summary_total_scan');
        const summaryTotalPacker = document.getElementById('summary_total_packer');
        const summaryAvgHour = document.getElementById('summary_avg_hour');
        let fpFrom = null;
        let fpTo = null;

        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
        }

        const updateSummary = (data) => {
            const rows = Array.isArray(data) ? data : [];
            const totalScan = rows.reduce((sum, row) => sum + (Number(row.total_scan) || 0), 0);
            const avgHour = rows.length
                ? (rows.reduce((sum, row) => sum + (Number(row.avg_per_hour) || 0), 0) / rows.length)
                : 0;
            const totalPacker = new Set(rows.map((row) => row.packer)).size;
            if (summaryTotalScan) summaryTotalScan.textContent = totalScan.toLocaleString('id-ID');
            if (summaryTotalPacker) summaryTotalPacker.textContent = totalPacker.toString();
            if (summaryAvgHour) summaryAvgHour.textContent = avgHour.toFixed(2);
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: false,
            searching: false,
            ajax: {
                url: dataUrl,
                dataSrc: function(json) {
                    const data = json?.data || [];
                    updateSummary(data);
                    return data;
                },
                data: function(params) {
                    params.date_from = dateFromEl?.value || '';
                    params.date_to = dateToEl?.value || '';
                    params.packer_id = packerSelect?.value || '';
                    params.q = searchInput?.value || '';
                }
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'date' },
                { data: 'packer' },
                { data: 'total_scan', className: 'text-end' },
                { data: 'unique_scan', className: 'text-end' },
                { data: 'avg_per_hour', className: 'text-end' },
                { data: 'first_scan' },
                { data: 'last_scan' },
            ]
        });

        const reloadTable = () => dt.ajax.reload();

        applyBtn?.addEventListener('click', () => {
            reloadTable();
        });

        packerSelect?.addEventListener('change', reloadTable);

        searchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                reloadTable();
            }
        });

        resetBtn?.addEventListener('click', () => {
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = todayStr || '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = todayStr || '';
            if (packerSelect) packerSelect.value = '';
            if (searchInput) searchInput.value = '';
            reloadTable();
        });
    });
</script>
@endpush
