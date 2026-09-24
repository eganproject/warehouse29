@extends('layouts.admin')

@section('title', 'Toko & Channel')
@section('page_title', 'Toko & Channel')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.toko-channel.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.toko-channel.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.toko-channel.index', 'delete');
    $tabs = [
        'toko' => ['label' => 'Toko', 'placeholder' => 'Search toko'],
        'channel' => ['label' => 'Channel', 'placeholder' => 'Search channel'],
    ];
@endphp

@section('content')
<ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x mb-6" role="tablist">
    @foreach($tabs as $type => $tab)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab_{{ $type }}" data-type="{{ $type }}" type="button" role="tab">
                {{ $tab['label'] }}
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content">
    @foreach($tabs as $type => $tab)
        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab_{{ $type }}" role="tabpanel">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div class="d-flex align-items-center position-relative my-1">
                            <span class="svg-icon svg-icon-1 position-absolute ms-6">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                                    <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17.53333 14.4667 17.53333 11C17.53333 7.53333 14.4667 5 11 5Z" fill="black" />
                                </svg>
                            </span>
                            <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="{{ $tab['placeholder'] }}" data-search="{{ $type }}" />
                        </div>
                    </div>
                    <div class="card-toolbar">
                        @if($canCreate)
                            <button type="button" class="btn btn-primary btn-open-create" data-type="{{ $type }}">
                                Add {{ $tab['label'] }}
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body py-6">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="table_{{ $type }}" data-type="{{ $type }}">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Jumlah Resi</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="modal fade" id="modal_toko_channel_form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="modal_toko_channel_title">Add Toko</h2>
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
                <form class="form" id="toko_channel_form">
                    @csrf
                    <input type="hidden" id="form_type" value="toko" />
                    <input type="hidden" id="form_id" />
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Nama</label>
                        <input type="text" class="form-control form-control-solid" name="name" id="form_name" required />
                        <div class="invalid-feedback d-block" id="error_name"></div>
                    </div>
                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Simpan</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const csrfToken = '{{ csrf_token() }}';
    const dataTpl   = '{{ route('admin.masterdata.toko-channel.data', ':type') }}';
    const storeTpl  = '{{ route('admin.masterdata.toko-channel.store', ':type') }}';
    const updateTpl = '{{ route('admin.masterdata.toko-channel.update', [':type', '0']) }}'.replace(/\/0$/, '/:id');
    const deleteTpl = '{{ route('admin.masterdata.toko-channel.destroy', [':type', '0']) }}'.replace(/\/0$/, '/:id');
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const typeLabels = { toko: 'Toko', channel: 'Channel' };

    document.addEventListener('DOMContentLoaded', () => {
        if (!$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const form = document.getElementById('toko_channel_form');
        const modalEl = document.getElementById('modal_toko_channel_form');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const formType = document.getElementById('form_type');
        const formId = document.getElementById('form_id');
        const formName = document.getElementById('form_name');
        const titleEl = document.getElementById('modal_toko_channel_title');
        const errorName = document.getElementById('error_name');

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const refreshMenus = () => {
            if (window.KTMenu) {
                KTMenu.createInstances();
            }
        };

        const notify = (title, message, icon) => {
            if (typeof Swal !== 'undefined') {
                Swal.fire(title, message, icon);
            } else {
                alert(message);
            }
        };

        const parseJson = async (res) => {
            const text = await res.text();
            try { return JSON.parse(text); } catch (err) { return {}; }
        };

        const tables = {};
        Object.keys(typeLabels).forEach((type) => {
            const tableEl = $(`#table_${type}`);
            const searchInput = document.querySelector(`[data-search="${type}"]`);
            if (!tableEl.length) return;

            const dt = tableEl.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                ordering: false,
                ajax: {
                    url: dataTpl.replace(':type', type),
                    dataSrc: 'data',
                    data: function(params) {
                        params.q = searchInput?.value || '';
                    }
                },
                columns: [
                    { data: 'id' },
                    { data: 'name', render: (data) => escapeHtml(data) },
                    { data: 'resis_count' },
                    { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data, t, row) => {
                        const editItem = canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}" data-name="${escapeHtml(row.name)}">Edit</a></div>` : '';
                        const delItem = canDelete ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>` : '';
                        const actions = `${editItem}${delItem}`;
                        if (!actions) return '';
                        return `
                            <div class="text-end">
                                <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                    Actions
                                    <span class="svg-icon svg-icon-5 m-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                        </svg>
                                    </span>
                                </a>
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                                    ${actions}
                                </div>
                            </div>
                        `;
                    }}
                ]
            });
            dt.on('draw', refreshMenus);
            tables[type] = dt;

            let searchTimer = null;
            searchInput?.addEventListener('keyup', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => dt.ajax.reload(), 300);
            });

            tableEl.on('click', '.btn-edit', function (e) {
                e.preventDefault();
                openForm(type, this.getAttribute('data-id'), this.getAttribute('data-name'));
            });

            tableEl.on('click', '.btn-delete', function (e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                if (id) confirmDelete(type, id);
            });
        });

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => {
                const dt = tables[tab.getAttribute('data-type')];
                dt?.columns.adjust();
                refreshMenus();
            });
        });

        const clearErrors = () => {
            if (errorName) errorName.textContent = '';
        };

        const openForm = (type, id = '', name = '') => {
            if (!form) return;
            form.reset();
            clearErrors();
            formType.value = type;
            formId.value = id || '';
            formName.value = name || '';
            if (titleEl) titleEl.textContent = `${id ? 'Edit' : 'Add'} ${typeLabels[type]}`;
            modal?.show();
        };

        document.querySelectorAll('.btn-open-create').forEach((btn) => {
            btn.addEventListener('click', () => openForm(btn.getAttribute('data-type')));
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            const type = formType.value;
            const id = formId.value;
            const url = id
                ? updateTpl.replace(':type', type).replace(':id', id)
                : storeTpl.replace(':type', type);
            const formData = new FormData(form);
            if (id) formData.append('_method', 'PUT');
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const json = await parseJson(res);
                if (!res.ok) {
                    if (json?.errors?.name && errorName) {
                        errorName.textContent = json.errors.name[0];
                    } else {
                        notify('Error', json?.message || 'Gagal menyimpan data', 'error');
                    }
                    return;
                }

                notify('Berhasil', json?.message || 'Data tersimpan', 'success');
                modal?.hide();
                tables[type]?.ajax.reload(null, false);
            } catch (error) {
                notify('Error', 'Gagal menyimpan data', 'error');
            }
        });

        const confirmDelete = (type, id) => {
            const doDelete = async () => {
                try {
                    const res = await fetch(deleteTpl.replace(':type', type).replace(':id', id), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: new URLSearchParams({ _method: 'DELETE' }),
                    });
                    const json = await parseJson(res);
                    if (!res.ok) {
                        notify('Error', json?.message || 'Gagal menghapus data', 'error');
                        return;
                    }
                    notify('Berhasil', json?.message || 'Data terhapus', 'success');
                    tables[type]?.ajax.reload(null, false);
                } catch (error) {
                    notify('Error', 'Gagal menghapus data', 'error');
                }
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: `Hapus ${typeLabels[type].toLowerCase()}?`,
                    text: 'Data yang dihapus tidak bisa dikembalikan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (result.isConfirmed) doDelete();
                });
            } else if (confirm('Hapus data ini?')) {
                doDelete();
            }
        };
    });
</script>
@endpush
