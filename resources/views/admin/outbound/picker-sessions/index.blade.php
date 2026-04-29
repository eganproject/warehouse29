@extends('layouts.admin')

@section('title', 'History QC Scan')
@section('page_title', 'History QC Scan')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6 pb-4 flex-wrap gap-3">
        <div class="card-title flex-wrap gap-2">
            {{-- Search --}}
            <div class="d-flex align-items-center position-relative my-1">
                <i class="bi bi-search position-absolute ms-3 text-gray-500 fs-5"></i>
                <input type="text" class="form-control form-control-solid w-200px ps-10" placeholder="Cari kode / SKU / petugas" id="qc_search" />
            </div>
            {{-- Date From --}}
            <input type="text" class="form-control form-control-solid w-135px" id="filter_date_from" placeholder="Dari" value="{{ $today ?? '' }}" />
            {{-- Date To --}}
            <input type="text" class="form-control form-control-solid w-135px" id="filter_date_to" placeholder="Sampai" value="{{ $today ?? '' }}" />
            {{-- User --}}
            <select id="filter_picker_user" class="form-select form-select-solid w-175px">
                <option value="">Semua Petugas</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            {{-- Status --}}
            <select id="filter_picker_status" class="form-select form-select-solid w-150px">
                <option value="">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="closed">Closed</option>
            </select>
            {{-- Apply / Reset --}}
            <button type="button" class="btn btn-primary px-4" id="filter_apply">
                <i class="bi bi-funnel me-1"></i>Terapkan
            </button>
            <button type="button" class="btn btn-light px-4" id="filter_reset">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
            </button>
        </div>
    </div>

    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4" id="picker_sessions_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Kode Sesi</th>
                        <th>Petugas QC</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Progres Resi</th>
                        <th>Progres Qty QC</th>
                        <th class="text-end">Qty Transit</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="modal_qc_session_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="qc_detail_title">Detail Sesi QC</h5>
                    <div class="text-muted fs-7" id="qc_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Info pills --}}
                <div class="d-flex flex-wrap gap-2 mb-5" id="qc_detail_pills"></div>

                {{-- Resi table --}}
                <h6 class="fw-bold text-gray-700 mb-3">
                    <i class="bi bi-upc-scan me-1 text-primary"></i>Progres Resi QC
                </h6>
                <div class="table-responsive mb-6">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="5%">No</th>
                                <th>No Resi</th>
                                <th>ID Pesanan</th>
                                <th class="text-end">Qty QC</th>
                                <th class="text-center">Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="qc_detail_resi_body">
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                        </tbody>
                    </table>
                </div>

                {{-- Items table --}}
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h6 class="fw-bold text-gray-700 mb-0">
                        <i class="bi bi-box-seam me-1 text-primary"></i>Detail Resi dan SKU
                    </h6>
                    <div class="d-flex align-items-center position-relative">
                        <i class="bi bi-search position-absolute ms-3 text-gray-500 fs-5"></i>
                        <input type="text" class="form-control form-control-solid w-250px ps-10" id="qc_detail_items_search" placeholder="Cari resi / SKU / nama" />
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="5%">No</th>
                                <th>Resi</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end">Scan / Wajib</th>
                                <th>Status QC</th>
                            </tr>
                        </thead>
                        <tbody id="qc_detail_items_body">
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data.</td></tr>
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
    const dataUrl      = '{{ $dataUrl }}';
    const deleteUrlTpl = '{{ route('admin.outbound.picker-sessions.destroy', ':id') }}';
    const csrfToken    = '{{ csrf_token() }}';
    const todayStr     = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        /* ── DOM refs ─────────────────────────────────────────── */
        const tableEl       = $('#picker_sessions_table');
        const searchInput   = document.getElementById('qc_search');
        const userSelect    = document.getElementById('filter_picker_user');
        const statusSelect  = document.getElementById('filter_picker_status');
        const dateFromEl    = document.getElementById('filter_date_from');
        const dateToEl      = document.getElementById('filter_date_to');
        const applyBtn      = document.getElementById('filter_apply');
        const resetBtn      = document.getElementById('filter_reset');

        const detailModalEl = document.getElementById('modal_qc_session_detail');
        const detailModal   = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const detailTitleEl     = document.getElementById('qc_detail_title');
        const detailSubtitleEl  = document.getElementById('qc_detail_subtitle');
        const detailPillsEl     = document.getElementById('qc_detail_pills');
        const detailResiBodyEl  = document.getElementById('qc_detail_resi_body');
        const detailItemsBodyEl = document.getElementById('qc_detail_items_body');
        const detailItemsSearchEl = document.getElementById('qc_detail_items_search');

        /* ── Row data store ───────────────────────────────────── */
        const rowDataStore = {};
        let activeDetailRow = null;

        /* ── HTML escape helper ───────────────────────────────── */
        const esc = v => String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

        /* ── Select2 init ─────────────────────────────────────── */
        const select2Safe = (el, placeholder) => {
            if (el && typeof $ !== 'undefined' && $.fn.select2) {
                $(el).select2({ placeholder, allowClear: true, width: '100%' })
                    .on('select2:opening select2:closing select2:close', e => e.stopPropagation());
            }
        };
        select2Safe(userSelect, 'Semua Petugas');
        select2Safe(statusSelect, 'Semua Status');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        /* ── Flatpickr ────────────────────────────────────────── */
        let fpFrom = null, fpTo = null;
        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
                if (todayStr && !dateFromEl.value) fpFrom.setDate(todayStr, true);
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
                if (todayStr && !dateToEl.value) fpTo.setDate(todayStr, true);
            }
        }

        /* ── DataTable ────────────────────────────────────────── */
        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[3, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data(params) {
                    params.q          = searchInput?.value || '';
                    params.user_id    = userSelect?.value  || '';
                    params.status     = statusSelect?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value)   params.date_to   = dateToEl.value;
                }
            },
            columns: [
                /* 0 – Kode Sesi */
                {
                    data: 'code',
                    render(data, type, row) {
                        return `<span class="fw-bold font-monospace fs-7">${esc(data) || '-'}</span>
                                <div class="text-muted fs-8">#${esc(String(row.id))}</div>`;
                    }
                },
                /* 1 – Petugas QC */
                { data: 'picker', render: data => esc(data) || '-' },
                /* 2 – Status */
                {
                    data: 'status',
                    render(data) {
                        if (data === 'closed') {
                            return '<span class="badge badge-light-success"><i class="bi bi-check-circle me-1"></i>Closed</span>';
                        }
                        return '<span class="badge badge-light-primary"><i class="bi bi-play-circle me-1"></i>Aktif</span>';
                    }
                },
                /* 3 – Tanggal */
                {
                    data: 'started_at',
                    render(data, type, row) {
                        const started   = esc(data) || '-';
                        const lastScan = row.last_scan_at ? `<div class="text-muted fs-8"><i class="bi bi-upc-scan me-1"></i>${esc(row.last_scan_at)}</div>` : '';
                        return `<span class="fs-7">${started}</span>${lastScan}`;
                    }
                },
                /* 4 – Progres Resi */
                {
                    data: 'resis',
                    orderable: false,
                    searchable: false,
                    render(data, type, row) {
                        /* Store row for the detail modal */
                        rowDataStore[String(row.id)] = row;

                        const count = Array.isArray(data) ? data.length : 0;
                        const completed = Number(row.resi_completed_count || 0);
                        const inProgress = Number(row.resi_in_progress_count || 0);
                        const badgeClass = inProgress > 0 ? 'btn-light-warning' : (count > 0 ? 'btn-light-success' : 'btn-light');
                        return `<button class="btn btn-sm ${badgeClass} btn-detail-session px-3" data-id="${esc(String(row.id))}">
                                    <i class="bi bi-upc-scan me-1"></i>${completed}/${count} Resi selesai
                                </button>
                                <div class="text-muted fs-9 mt-1">${inProgress} belum lengkap</div>`;
                    }
                },
                /* 5 – Progres Qty QC */
                {
                    data: 'qc_progress',
                    orderable: false,
                    render(data, type, row) {
                        const pct = Number(data || 0);
                        const scanned = Number(row.scanned_qty || 0).toLocaleString('id-ID');
                        const required = Number(row.required_qty || 0).toLocaleString('id-ID');
                        const barClass = pct >= 100 ? 'bg-success' : 'bg-warning';
                        return `<div class="d-flex align-items-center gap-2" style="min-width:180px">
                                    <div class="progress flex-grow-1 h-6px bg-light">
                                        <div class="progress-bar ${barClass}" style="width:${pct}%"></div>
                                    </div>
                                    <span class="fw-semibold fs-8">${pct}%</span>
                                </div>
                                <div class="text-muted fs-9">${scanned}/${required} qty</div>`;
                    }
                },
                /* 6 – Qty Transit */
                {
                    data: 'qty',
                    className: 'text-end',
                    render: data => `<span class="fw-bold">${Number(data || 0).toLocaleString('id-ID')}</span>`
                },
                /* 7 – Aksi */
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render(data, type, row) {
                        if (row?.status !== 'active') return '-';
                        const actions = [];
                        if (Number(row?.qty || 0) === 0) {
                            actions.push(`<button class="btn btn-sm btn-light-danger btn-delete" data-id="${esc(String(data))}"><i class="bi bi-trash me-1"></i>Hapus</button>`);
                        }
                        return actions.length ? `<div class="d-flex justify-content-end gap-2">${actions.join('')}</div>` : '-';
                    }
                },
            ]
        });

        const reloadTable = () => dt.ajax.reload(null, false);

        /* ── Search debounce ──────────────────────────────────── */
        let searchTimer = null;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadTable, 400);
        });

        /* ── Filter buttons ───────────────────────────────────── */
        applyBtn?.addEventListener('click', reloadTable);

        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (userSelect) {
                if (typeof $ !== 'undefined' && $(userSelect).data('select2')) {
                    $(userSelect).val('').trigger('change.select2');
                } else {
                    userSelect.value = '';
                }
            }
            if (statusSelect) {
                if (typeof $ !== 'undefined' && $(statusSelect).data('select2')) {
                    $(statusSelect).val('').trigger('change.select2');
                } else {
                    statusSelect.value = '';
                }
            }
            if (fpFrom && todayStr) fpFrom.setDate(todayStr, true);
            else if (dateFromEl) dateFromEl.value = todayStr || '';
            if (fpTo && todayStr)   fpTo.setDate(todayStr, true);
            else if (dateToEl)   dateToEl.value = todayStr || '';
            reloadTable();
        });

        /* ── Detail modal ─────────────────────────────────────── */
        const pendingResis = row => (Array.isArray(row?.resis) ? row.resis : [])
            .filter(r => (r.status || 'in_progress') !== 'completed');

        const renderDetail = row => {
            if (!row) return;
            /* Title / subtitle */
            if (detailTitleEl)    detailTitleEl.textContent    = row.code || '-';
            if (detailSubtitleEl) detailSubtitleEl.textContent = `${row.picker || '-'} • ${row.started_at || '-'}`;

            /* Info pills */
            if (detailPillsEl) {
                const statusBadge = row.status === 'closed'
                    ? `<span class="badge badge-light-success"><i class="bi bi-check-circle me-1"></i>Closed</span>`
                    : `<span class="badge badge-light-primary"><i class="bi bi-play-circle me-1"></i>Aktif</span>`;
                const lastScanPill = row.last_scan_at
                    ? pill('bi-upc-scan', 'text-success', 'Last Scan', esc(row.last_scan_at))
                    : '';
                detailPillsEl.innerHTML = [
                    statusBadge,
                    pill('bi-tag',          'text-primary',  'Kode',     esc(row.code)),
                    pill('bi-person',        'text-info',     'Petugas',  esc(row.picker)),
                    pill('bi-calendar',      'text-gray-600', 'Mulai',    esc(row.started_at)),
                    lastScanPill,
                    pill('bi-upc-scan',      'text-primary',  'Resi Selesai', `${row.resi_completed_count ?? 0}/${row.resi_count ?? 0}`),
                    pill('bi-box-seam',      'text-warning',  'Qty QC', `${row.scanned_qty ?? 0}/${row.required_qty ?? 0}`),
                    pill('bi-graph-up',      'text-success',  'Progress', `${row.qc_progress ?? 0}%`),
                ].filter(Boolean).join('');
            }

            /* Resi rows */
            if (detailResiBodyEl) {
                const resis = pendingResis(row);
                if (resis.length === 0) {
                    detailResiBodyEl.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada resi yang masih belum selesai QC.</td></tr>`;
                } else {
                    detailResiBodyEl.innerHTML = resis.map((r, i) => `
                        <tr>
                            <td class="text-muted">${i + 1}</td>
                            <td><span class="fw-bold font-monospace text-primary fs-7">${esc(r.no_resi)}</span></td>
                            <td>${esc(r.id_pesanan)}</td>
                            <td class="text-end fw-semibold">${Number(r.scanned_qty || 0).toLocaleString('id-ID')} / ${Number(r.required_qty || 0).toLocaleString('id-ID')}</td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-2">
                                    <div class="progress w-75px h-6px bg-light">
                                        <div class="progress-bar ${r.status === 'completed' ? 'bg-success' : 'bg-warning'}" style="width:${Number(r.progress || 0)}%"></div>
                                    </div>
                                    <span class="fs-9 fw-semibold">${Number(r.progress || 0)}%</span>
                                </div>
                            </td>
                            <td>${qcStatusBadge(r.status)}</td>
                        </tr>`).join('');
                }
            }

            /* Items rows */
            renderDetailItems(row);
        };

        const renderDetailItems = row => {
            if (!detailItemsBodyEl) return;
            const keyword = (detailItemsSearchEl?.value || '').trim().toLowerCase();
            const resis = Array.isArray(row?.resis) ? row.resis : [];
            const groups = resis.map(r => {
                const items = Array.isArray(r.items) ? r.items : [];
                const resiMatch = String(r.no_resi || '').toLowerCase().includes(keyword)
                    || String(r.id_pesanan || '').toLowerCase().includes(keyword)
                    || String(r.status || '').toLowerCase().includes(keyword);
                const filteredItems = keyword === ''
                    ? items
                    : (resiMatch ? items : items.filter(it => {
                        return String(it.sku || '').toLowerCase().includes(keyword)
                            || String(it.name || '').toLowerCase().includes(keyword)
                            || String(it.status || '').toLowerCase().includes(keyword);
                    }));
                return { ...r, items: filteredItems };
            }).filter(r => (Array.isArray(r.items) && r.items.length > 0));

            if (groups.length === 0) {
                detailItemsBodyEl.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada detail resi/SKU yang cocok.</td></tr>`;
            } else {
                let no = 1;
                detailItemsBodyEl.innerHTML = groups.map(r => {
                    const header = `
                        <tr class="bg-light">
                            <td class="text-muted">${no++}</td>
                            <td colspan="3">
                                <div class="fw-bold font-monospace text-primary">${esc(r.no_resi || '-')}</div>
                                <div class="text-muted fs-9">${esc(r.id_pesanan || '-')} · ${Number(r.scanned_qty || 0).toLocaleString('id-ID')} / ${Number(r.required_qty || 0).toLocaleString('id-ID')} qty · ${Number(r.progress || 0)}%</div>
                            </td>
                            <td class="text-end fw-semibold">${Number(r.scanned_qty || 0).toLocaleString('id-ID')} / ${Number(r.required_qty || 0).toLocaleString('id-ID')}</td>
                            <td>${qcStatusBadge(r.status)}</td>
                        </tr>`;
                    const itemRows = r.items.map(it => `
                        <tr>
                            <td></td>
                            <td class="text-muted fs-9">${esc(r.no_resi || '-')}</td>
                            <td><span class="fw-bold font-monospace">${esc(it.sku)}</span></td>
                            <td>${esc(it.name)}</td>
                            <td class="text-end fw-semibold">${Number(it.scanned_qty || 0).toLocaleString('id-ID')} / ${Number(it.required_qty || 0).toLocaleString('id-ID')}</td>
                            <td>${qcStatusBadge(it.status)}</td>
                        </tr>`).join('');
                    return header + itemRows;
                }).join('');
            }
        };

        tableEl.on('click', '.btn-detail-session', function() {
            const id  = this.getAttribute('data-id');
            const row = rowDataStore[id];
            if (!row || !detailModal) return;

            activeDetailRow = row;
            if (detailItemsSearchEl) detailItemsSearchEl.value = '';
            renderDetail(row);

            detailModal.show();
        });

        let detailSearchTimer = null;
        detailItemsSearchEl?.addEventListener('input', () => {
            clearTimeout(detailSearchTimer);
            detailSearchTimer = setTimeout(() => renderDetailItems(activeDetailRow), 200);
        });

        /* pill helper */
        function pill(icon, colorClass, label, value) {
            return `<span class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded bg-light">
                        <i class="bi ${esc(icon)} ${esc(colorClass)} fs-7"></i>
                        <span class="text-muted fs-8">${esc(label)}:</span>
                        <span class="fw-semibold fs-7">${value}</span>
                    </span>`;
        }

        function qcStatusBadge(status) {
            if (status === 'completed') {
                return '<span class="badge badge-light-success"><i class="bi bi-check-circle me-1"></i>Selesai</span>';
            }
            return '<span class="badge badge-light-warning"><i class="bi bi-hourglass-split me-1"></i>Belum Lengkap</span>';
        }

        /* ── Shared API action helper ─────────────────────────── */
        const apiAction = async ({ url, method = 'POST', body = null }) => {
            const opts = {
                method,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            };
            if (body) {
                opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                opts.body = body;
            }
            const res  = await fetch(url, opts);
            const text = await res.text();
            let json = null;
            try { json = JSON.parse(text); } catch { /* ignore */ }
            return { ok: res.ok, status: res.status, json };
        };

        const swalConfirm = async (title, text, confirmText, confirmClass = 'btn btn-primary') => {
            if (typeof Swal === 'undefined') return window.confirm(`${title}\n${text}`);
            const res = await Swal.fire({
                title, text, icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Batal',
                buttonsStyling: false,
                customClass: { confirmButton: confirmClass, cancelButton: 'btn btn-light' },
            });
            return res.isConfirmed;
        };

        const swalResult = (ok, msg) => {
            if (typeof Swal !== 'undefined') {
                Swal.fire(ok ? 'Berhasil' : 'Error', msg, ok ? 'success' : 'error');
            } else {
                alert(msg);
            }
        };

        const showInsufficientStock = (details) => {
            if (!Array.isArray(details) || !details.length || typeof Swal === 'undefined') return;
            const list = details.map(row => {
                const sku = row.sku || '-';
                const name = row.name ? ` • ${row.name}` : '';
                const available = row.available ?? '-';
                const required  = row.required  ?? '-';
                return `<li style="margin-bottom:6px"><strong>${sku}</strong>${name}<br><span style="color:#64748b">Tersedia ${available}, butuh ${required}</span></li>`;
            }).join('');
            Swal.fire({
                icon: 'error',
                title: 'Stok tidak mencukupi',
                html: `<div style="text-align:left;font-size:14px">Item berikut stoknya kurang:</div><ul style="text-align:left;padding-left:18px;margin-top:8px">${list}</ul>`,
            });
        };

        /* ── Delete handler ───────────────────────────────────── */
        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            const confirmed = await swalConfirm(
                'Hapus batch?',
                'Batch kosong akan dihapus.',
                'Hapus',
                'btn btn-danger'
            );
            if (!confirmed) return;
            try {
                const { ok, json } = await apiAction({
                    url: deleteUrlTpl.replace(':id', id),
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                if (!ok) {
                    swalResult(false, json?.message || 'Gagal menghapus sesi');
                    return;
                }
                swalResult(true, json?.message || 'Sesi berhasil dihapus');
                reloadTable();
            } catch {
                swalResult(false, 'Gagal menghapus sesi');
            }
        });
    });
</script>
@endpush
