@extends('layouts.admin')

@section('title', 'Laporan Picker')
@section('page_title', 'Laporan Picker')

@section('content')
<style>
    .report-shell {
        background: #f8fafc;
        border-radius: 18px;
        padding: 18px;
        border: 1px solid #e2e8f0;
    }
    .report-sheet {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        padding: 20px;
    }
    .report-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        border-bottom: 1px dashed #cbd5f5;
        padding-bottom: 14px;
        margin-bottom: 18px;
    }
    .report-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .report-meta {
        font-size: 12px;
        color: #64748b;
    }
    .report-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .report-table thead th {
        background: #f1f5f9;
        color: #475569;
        font-size: 11px;
        letter-spacing: 0.4px;
    }
    .report-table tbody td {
        vertical-align: top;
        font-size: 12px;
    }
    .report-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        background: #e2e8f0;
        color: #475569;
    }
    .detail-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .detail-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 12px;
        color: #475569;
    }
    .detail-card strong {
        display: block;
        font-size: 13px;
        color: #0f172a;
    }
</style>

<div class="report-shell">
<div class="report-sheet">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="report-header">
                <div>
                    <div class="report-title">Laporan Picker Harian</div>
                    <div class="report-meta">Ringkasan aktivitas picking per orang per hari.</div>
                </div>
                <div class="report-actions">
                    <div class="position-relative">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                            </svg>
                        </span>
                        <input type="text" class="form-control form-control-solid w-200px ps-14" placeholder="Search picker" data-kt-filter="search" />
                    </div>
                    <input type="text" class="form-control form-control-solid w-140px" id="filter_date_from" placeholder="Dari" />
                    <input type="text" class="form-control form-control-solid w-140px" id="filter_date_to" placeholder="Sampai" />
                    <button type="button" class="btn btn-light" id="filter_date_apply">Filter</button>
                    <button type="button" class="btn btn-light" id="filter_date_reset">Reset</button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5 report-table" id="picker_reports_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Tanggal</th>
                        <th>Picker</th>
                        <th>Batch</th>
                        <th>SKU</th>
                        <th>Qty</th>
                        <th>Jam</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="modal_report_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Detail Item Picker</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div class="detail-summary">
                    <div class="detail-card">
                        <span>Tanggal</span>
                        <strong id="detail_date">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Picker</span>
                        <strong id="detail_picker">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Batch</span>
                        <strong id="detail_batch">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>SKU</span>
                        <strong id="detail_sku">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Total Qty</span>
                        <strong id="detail_qty">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Jam Submit</span>
                        <strong id="detail_range">-</strong>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>SKU</th>
                                <th>Nama</th>
                                <th class="text-end">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="detail_items"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const detailUrl = '{{ route('admin.outbound.picker-reports.detail') }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picker_reports_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const dateApplyBtn = document.getElementById('filter_date_apply');
        const dateResetBtn = document.getElementById('filter_date_reset');
        const detailModalEl = document.getElementById('modal_report_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? '-';
        };
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

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'date' },
                { data: 'picker' },
                { data: 'batch_count' },
                { data: 'sku_count' },
                { data: 'qty' },
                { data: 'range' },
                { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                    return `<button type="button" class="btn btn-sm btn-light-primary btn-detail" data-date="${row.date}" data-user="${row.user_id}">Detail</button>`;
                }},
            ]
        });

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        dateApplyBtn?.addEventListener('click', reloadTable);
        dateResetBtn?.addEventListener('click', () => {
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            reloadTable();
        });

        tableEl.on('click', '.btn-detail', async function(e) {
            e.preventDefault();
            const date = this.getAttribute('data-date');
            const userId = this.getAttribute('data-user');
            if (!date || !userId) return;
            try {
                const url = `${detailUrl}?date=${encodeURIComponent(date)}&user_id=${encodeURIComponent(userId)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat detail', 'error');
                    return;
                }
                setText('detail_date', json.date);
                setText('detail_picker', json.picker);
                setText('detail_batch', json.batch_count);
                setText('detail_sku', json.sku_count);
                setText('detail_qty', json.qty);
                const range = `${json.first_submitted_at} - ${json.last_submitted_at}`;
                setText('detail_range', range);

                const items = json.items || [];
                const rows = items.map((row) => `
                    <tr>
                        <td>${row.sku || '-'}</td>
                        <td>${row.name || '-'}</td>
                        <td class="text-end">${row.qty || 0}</td>
                    </tr>
                `).join('');
                const tbody = document.getElementById('detail_items');
                if (tbody) {
                    tbody.innerHTML = rows || '<tr><td colspan="3" class="text-center text-muted">Tidak ada item.</td></tr>';
                }

                detailModal?.show();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal memuat detail', 'error');
            }
        });
    });
</script>
@endpush
