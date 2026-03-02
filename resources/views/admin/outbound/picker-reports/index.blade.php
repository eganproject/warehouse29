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
        font-family: "Times New Roman", Georgia, serif;
    }
    .report-letterhead {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        border-bottom: 2px solid #0f172a;
        padding-bottom: 14px;
        margin-bottom: 18px;
    }
    .report-brand {
        display: grid;
        gap: 4px;
    }
    .brand-name {
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .brand-sub {
        font-size: 11px;
        color: #475569;
        letter-spacing: 0.4px;
    }
    .report-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 6px;
        text-align: right;
    }
    .report-meta {
        font-size: 12px;
        color: #64748b;
        text-align: right;
    }
    .report-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }
    .meta-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 12px;
        color: #475569;
        background: #f8fafc;
    }
    .meta-card strong {
        display: block;
        color: #0f172a;
        font-size: 13px;
        margin-top: 4px;
    }
    .report-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        padding: 12px;
        border: 1px dashed #cbd5f5;
        border-radius: 12px;
        margin-bottom: 18px;
        background: #f8fafc;
    }
    .report-filters .form-control,
    .report-filters .form-select {
        font-size: 12px;
    }
    .report-table thead th {
        background: #f1f5f9;
        color: #475569;
        font-size: 11px;
        letter-spacing: 0.4px;
        border: 1px solid #cbd5e1 !important;
    }
    .report-table tbody td {
        vertical-align: top;
        font-size: 12px;
        border: 1px solid #e2e8f0;
    }
    .report-table {
        border: 1px solid #cbd5e1;
    }
    .report-table tbody tr:nth-child(even) {
        background: #f8fafc;
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
    .report-footer {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 24px;
        margin-top: 24px;
    }
    .sign-block {
        text-align: center;
        font-size: 12px;
        color: #475569;
    }
    .sign-line {
        margin: 40px auto 6px;
        height: 1px;
        width: 100%;
        background: #cbd5e1;
    }
</style>

<div class="report-shell">
<div class="report-sheet">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="report-letterhead">
                <div class="report-brand">
                    <div class="brand-name">Gudang 29</div>
                    <div class="brand-sub">Warehouse Operations Report</div>
                </div>
                <div>
                    <div class="report-title">Laporan Picker Harian</div>
                    <div class="report-meta">Ringkasan aktivitas picking per orang per hari</div>
                </div>
            </div>
            <div class="report-meta-grid">
                <div class="meta-card">
                    Periode
                    <strong id="meta_period">Semua</strong>
                </div>
                <div class="meta-card">
                    Divisi
                    <strong id="meta_divisi">Semua</strong>
                </div>
                <div class="meta-card">
                    Dicetak
                    <strong id="meta_generated">-</strong>
                </div>
            </div>
            <div class="report-filters">
                <div class="position-relative">
                    <span class="svg-icon svg-icon-1 position-absolute ms-6">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                            <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                        </svg>
                    </span>
                    <input type="text" class="form-control form-control-solid w-200px ps-14" placeholder="Cari picker" data-kt-filter="search" />
                </div>
                <select id="filter_divisi" class="form-select form-select-solid w-180px" data-control="select2" data-placeholder="Semua divisi">
                    <option value="">Semua Divisi</option>
                    @foreach($divisis as $divisi)
                        <option value="{{ $divisi->id }}">{{ $divisi->name }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control form-control-solid w-140px" id="filter_date_from" placeholder="Dari" />
                <input type="text" class="form-control form-control-solid w-140px" id="filter_date_to" placeholder="Sampai" />
                <button type="button" class="btn btn-light" id="filter_date_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_date_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <ul class="nav nav-tabs nav-line-tabs mb-6" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab_report_picker" role="tab">Ringkasan Picker</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#tab_report_sku" role="tab">Ringkasan SKU</a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_report_picker" role="tabpanel">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5 report-table" id="picker_reports_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Tanggal laporan berdasarkan waktu submit batch.">Tanggal</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Nama picker yang melakukan picking.">Picker</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah batch yang disubmit pada tanggal tersebut.">Batch</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah SKU unik yang dipick pada tanggal tersebut.">SKU</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Total qty semua item yang dipick.">Qty</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Total qty dibagi jumlah batch.">Rata-rata Qty/Batch</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Total SKU unik dibagi jumlah batch.">Rata-rata SKU/Batch</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Rata-rata durasi per batch (started hingga submitted).">Rata-rata Durasi</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Akumulasi durasi seluruh batch pada tanggal tersebut.">Total Durasi</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Produktivitas = total qty / total durasi (qty/jam).">Produktivitas</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Rentang waktu kerja: mulai batch pertama hingga submit terakhir.">Jam Kerja</th>
                                <th class="text-end" data-bs-toggle="tooltip" data-bs-placement="top" title="Lihat detail item per picker per tanggal.">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_report_sku" role="tabpanel">
                <div class="d-flex justify-content-end mb-4">
                    <div class="position-relative">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                            </svg>
                        </span>
                        <input type="text" class="form-control form-control-solid w-220px ps-14" id="search_sku" placeholder="Cari SKU / Nama item" />
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5 report-table" id="picker_sku_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Kode SKU yang dipick.">SKU</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Nama item/produk.">Nama</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Total qty dari SKU tersebut.">Total Qty</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah batch yang mengandung SKU tersebut.">Jumlah Batch</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah picker unik yang mengambil SKU ini.">Jumlah Picker</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Total qty dibagi jumlah batch.">Rata-rata Qty/Batch</th>
                                <th data-bs-toggle="tooltip" data-bs-placement="top" title="Daftar picker yang mengambil SKU ini beserta qtynya.">Picker (Qty)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="report-footer">
        <div class="sign-block">
            Disiapkan oleh
            <div class="sign-line"></div>
            (Admin Gudang)
        </div>
        <div class="sign-block">
            Disetujui oleh
            <div class="sign-line"></div>
            (Supervisor)
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
                        <span>Rata-rata Qty/Batch</span>
                        <strong id="detail_avg_qty">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Rata-rata SKU/Batch</span>
                        <strong id="detail_avg_sku">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Rata-rata Durasi</span>
                        <strong id="detail_avg_duration">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Total Durasi</span>
                        <strong id="detail_total_duration">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Produktivitas</span>
                        <strong id="detail_productivity">-</strong>
                    </div>
                    <div class="detail-card">
                        <span>Jam Kerja</span>
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
    const skuUrl = '{{ route('admin.outbound.picker-reports.sku') }}';
    const detailUrl = '{{ route('admin.outbound.picker-reports.detail') }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picker_reports_table');
        const skuTableEl = $('#picker_sku_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const skuSearchInput = document.getElementById('search_sku');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const divisiSelect = document.getElementById('filter_divisi');
        const dateApplyBtn = document.getElementById('filter_date_apply');
        const dateResetBtn = document.getElementById('filter_date_reset');
        const detailModalEl = document.getElementById('modal_report_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const metaPeriod = document.getElementById('meta_period');
        const metaDivisi = document.getElementById('meta_divisi');
        const metaGenerated = document.getElementById('meta_generated');
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? '-';
        };
        let hasFilterApplied = false;
        let fpFrom = null;
        let fpTo = null;

        if (divisiSelect && typeof $ !== 'undefined' && $.fn.select2) {
            $(divisiSelect).select2({ placeholder: 'Semua Divisi', allowClear: true, width: '100%' })
                .on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                new bootstrap.Tooltip(el);
            });
        }

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

        const makeAjax = (url, getSearch) => (data, callback) => {
            if (!hasFilterApplied) {
                callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                return;
            }
            data.q = getSearch ? (getSearch() || '') : '';
            data.divisi_id = divisiSelect?.value || '';
            if (dateFromEl?.value) data.date_from = dateFromEl.value;
            if (dateToEl?.value) data.date_to = dateToEl.value;

            if (typeof $ !== 'undefined') {
                $.ajax({
                    url,
                    data,
                    dataType: 'json',
                    success: (res) => callback(res),
                    error: () => callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] }),
                });
            } else {
                callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
            }
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            deferLoading: 0,
            ajax: makeAjax(dataUrl, () => searchInput?.value || ''),
            language: {
                emptyTable: 'Silakan gunakan filter untuk menampilkan data.',
                zeroRecords: 'Data tidak ditemukan.',
            },
            columns: [
                { data: 'date' },
                { data: 'picker' },
                { data: 'batch_count' },
                { data: 'sku_count' },
                { data: 'qty' },
                { data: 'avg_qty' },
                { data: 'avg_sku' },
                { data: 'avg_duration' },
                { data: 'total_duration' },
                { data: 'productivity' },
                { data: 'range' },
                { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                    return `<button type="button" class="btn btn-sm btn-light-primary btn-detail" data-date="${row.date}" data-user="${row.user_id}">Detail</button>`;
                }},
            ]
        });

        const dtSku = skuTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'asc']],
            deferLoading: 0,
            ajax: makeAjax(skuUrl, () => skuSearchInput?.value || ''),
            language: {
                emptyTable: 'Silakan gunakan filter untuk menampilkan data.',
                zeroRecords: 'Data tidak ditemukan.',
            },
            columns: [
                { data: 'sku' },
                { data: 'name' },
                { data: 'total_qty' },
                { data: 'batch_count' },
                { data: 'picker_count' },
                { data: 'avg_qty' },
                { data: 'picker_list' },
            ]
        });

        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => {
                dt.columns.adjust();
                dtSku.columns.adjust();
            });
        });

        const reloadTable = () => dt.ajax.reload();
        const reloadSkuTable = () => dtSku.ajax.reload();
        searchInput?.addEventListener('keyup', () => {
            if (!hasFilterApplied) return;
            reloadTable();
        });
        skuSearchInput?.addEventListener('keyup', () => {
            if (!hasFilterApplied) return;
            reloadSkuTable();
        });
        dateApplyBtn?.addEventListener('click', () => {
            hasFilterApplied = true;
            updateMeta();
            reloadTable();
            reloadSkuTable();
        });
        dateResetBtn?.addEventListener('click', () => {
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            if (divisiSelect) {
                divisiSelect.value = '';
                if (typeof $ !== 'undefined' && $(divisiSelect).data('select2')) {
                    $(divisiSelect).val('').trigger('change.select2');
                }
            }
            if (searchInput) searchInput.value = '';
            if (skuSearchInput) skuSearchInput.value = '';
            hasFilterApplied = false;
            updateMeta();
            dt.clear().draw();
            dtSku.clear().draw();
        });

        const updateMeta = () => {
            const from = dateFromEl?.value || '';
            const to = dateToEl?.value || '';
            let period = hasFilterApplied ? 'Semua' : 'Belum difilter';
            if (from && to) {
                period = `${from} s/d ${to}`;
            } else if (from) {
                period = `Mulai ${from}`;
            } else if (to) {
                period = `Sampai ${to}`;
            }
            if (metaPeriod) metaPeriod.textContent = period;

            let divisiText = 'Semua';
            if (divisiSelect) {
                const selected = divisiSelect.options[divisiSelect.selectedIndex];
                if (selected && selected.value) {
                    divisiText = selected.textContent || 'Semua';
                }
            }
            if (metaDivisi) metaDivisi.textContent = divisiText;

            if (metaGenerated) {
                const now = new Date();
                const formatted = now.toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' });
                metaGenerated.textContent = formatted;
            }
        };

        updateMeta();
        dateApplyBtn?.addEventListener('click', updateMeta);
        dateResetBtn?.addEventListener('click', updateMeta);
        divisiSelect?.addEventListener('change', updateMeta);

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
                setText('detail_avg_qty', json.avg_qty ?? '-');
                setText('detail_avg_sku', json.avg_sku ?? '-');
                setText('detail_avg_duration', json.avg_duration ?? '-');
                setText('detail_total_duration', json.total_duration ?? '-');
                setText('detail_productivity', json.productivity ?? '-');
                const range = `${json.first_started_at || '-'} - ${json.last_submitted_at || '-'}`;
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
