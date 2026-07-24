@extends('layouts.admin')

@section('title', 'Akses API')
@section('page_title', 'Akses API')

@php
    use App\Support\Permission as Perm;
    $permissionRoute = 'admin.masterdata.api-ip-allowlists.index';
    $canCreate = Perm::can(auth()->user(), $permissionRoute, 'create');
    $canUpdate = Perm::can(auth()->user(), $permissionRoute, 'update');
    $canDelete = Perm::can(auth()->user(), $permissionRoute, 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <input class="form-control form-control-solid w-300px" id="api_ip_search" placeholder="Cari IP atau keterangan">
        </div>
        <div class="card-toolbar">
            @if($canCreate)<button class="btn btn-primary" id="api_ip_create" data-bs-toggle="modal" data-bs-target="#api_ip_modal">Tambah IP</button>@endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="alert alert-info d-flex align-items-center p-5 mb-6">
            <span class="svg-icon svg-icon-2hx svg-icon-info me-4"><i class="fa-solid fa-shield-halved fs-2"></i></span>
            <div class="d-flex flex-column"><span class="fw-bold">Akses API hanya dari IP aktif pada daftar ini.</span><span class="text-muted">Gunakan satu IP atau CIDR, contoh: <code>203.0.113.10</code> atau <code>203.0.113.0/24</code>. Penghapusan akan menonaktifkan data agar riwayat tetap tersimpan.</span></div>
        </div>
        <div class="table-responsive"><table class="table align-middle table-row-dashed fs-6 gy-5" id="api_ip_table">
            <thead><tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase"><th>IP / CIDR</th><th>Keterangan</th><th>Status</th><th>Kedaluwarsa</th><th>Terakhir Diubah</th><th class="text-end">Aksi</th></tr></thead><tbody></tbody>
        </table></div>
    </div>
</div>

<div class="modal fade" id="api_ip_modal" tabindex="-1"><div class="modal-dialog modal-dialog-centered mw-600px"><div class="modal-content">
    <div class="modal-header"><h2 class="fw-bolder" id="api_ip_title">Tambah IP Allowlist</h2><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="api_ip_form"><input type="hidden" id="api_ip_id">
        <div class="mb-5"><label class="required form-label">IP Address / CIDR</label><input class="form-control" id="api_ip_address" maxlength="50" placeholder="203.0.113.10 atau 203.0.113.0/24" required><div class="invalid-feedback d-block" id="api_ip_address_error"></div></div>
        <div class="mb-5"><label class="required form-label">Keterangan</label><input class="form-control" id="api_ip_name" maxlength="100" placeholder="Contoh: Server pusat production" required><div class="invalid-feedback d-block" id="api_ip_name_error"></div></div>
        <div class="mb-5"><label class="form-label">Catatan</label><textarea class="form-control" id="api_ip_note" rows="3" maxlength="1000"></textarea><div class="invalid-feedback d-block" id="api_ip_note_error"></div></div>
        <div class="mb-5"><label class="form-label">Berlaku sampai</label><input type="datetime-local" class="form-control" id="api_ip_expires_at"><div class="form-text">Kosongkan jika tidak memiliki masa berlaku.</div><div class="invalid-feedback d-block" id="api_ip_expires_at_error"></div></div>
        <div class="form-check form-switch form-check-custom form-check-solid mb-7"><input class="form-check-input" type="checkbox" id="api_ip_active" checked><label class="form-check-label" for="api_ip_active">Aktifkan akses dari IP ini</label></div>
        <div class="text-end"><button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
    </form></div>
</div></div></div>
@endsection

@push('scripts')
<script>
const apiIpUrls = { data: '{{ route('admin.masterdata.api-ip-allowlists.data') }}', store: '{{ route('admin.masterdata.api-ip-allowlists.store') }}', update: '{{ route('admin.masterdata.api-ip-allowlists.update', ':id') }}', destroy: '{{ route('admin.masterdata.api-ip-allowlists.destroy', ':id') }}' };
const apiIpCsrf = '{{ csrf_token() }}';
const apiIpPermissions = { update: {{ $canUpdate ? 'true' : 'false' }}, delete: {{ $canDelete ? 'true' : 'false' }} };
document.addEventListener('DOMContentLoaded', () => {
    const tableEl = $('#api_ip_table'), search = document.getElementById('api_ip_search'), form = document.getElementById('api_ip_form'), modal = new bootstrap.Modal(document.getElementById('api_ip_modal'));
    const idEl = document.getElementById('api_ip_id'), addressEl = document.getElementById('api_ip_address'), nameEl = document.getElementById('api_ip_name'), noteEl = document.getElementById('api_ip_note'), expiresEl = document.getElementById('api_ip_expires_at'), activeEl = document.getElementById('api_ip_active'), titleEl = document.getElementById('api_ip_title');
    const esc = value => String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const table = tableEl.DataTable({ processing:true, serverSide:true, dom:'rtip', ajax:{url:apiIpUrls.data,dataSrc:'data',data:p=>p.q=search?.value||''}, columns:[
        {data:'ip_address',render:value=>`<code>${esc(value)}</code>`}, {data:'name',render:(value,t,row)=>`${esc(value)}${row.note ? `<div class="text-muted fs-8">${esc(row.note)}</div>` : ''}`},
        {data:'is_active',render:(value,t,row)=> value && (!row.expires_at || new Date(row.expires_at) >= new Date()) ? '<span class="badge badge-light-success">Aktif</span>' : '<span class="badge badge-light-secondary">Tidak aktif</span>'},
        {data:'expires_at',render:value=>value ? esc(value.replace('T',' ')) : '<span class="text-muted">Tanpa batas</span>'},
        {data:'updated_at',render:(value,t,row)=>`${esc(value || '-')}${row.updated_by_name ? `<div class="text-muted fs-8">${esc(row.updated_by_name)}</div>` : ''}`},
        {data:'id',className:'text-end',orderable:false,searchable:false,render:(id,t,row)=> { const edit=apiIpPermissions.update ? `<a href="#" class="menu-link px-3 api-ip-edit" data-row="${encodeURIComponent(JSON.stringify(row))}">Edit</a>` : ''; const disable=apiIpPermissions.delete && row.is_active ? `<a href="#" class="menu-link px-3 text-danger api-ip-disable" data-id="${id}">Nonaktifkan</a>` : ''; return (edit||disable) ? `<div class="menu-item px-3">${edit}</div><div class="menu-item px-3">${disable}</div>` : ''; }}
    ]});
    search?.addEventListener('keyup',()=>table.ajax.reload());
    document.getElementById('api_ip_create')?.addEventListener('click',()=>{ form.reset(); idEl.value=''; activeEl.checked=true; titleEl.textContent='Tambah IP Allowlist'; });
    tableEl.on('click','.api-ip-edit',function(e){ e.preventDefault(); const row=JSON.parse(decodeURIComponent(this.dataset.row)); idEl.value=row.id; addressEl.value=row.ip_address||''; nameEl.value=row.name||''; noteEl.value=row.note||''; expiresEl.value=row.expires_at||''; activeEl.checked=!!row.is_active; titleEl.textContent='Edit IP Allowlist'; modal.show(); });
    form.addEventListener('submit',async e=>{ e.preventDefault(); ['address','name','note','expires_at'].forEach(key=>document.getElementById(`api_ip_${key}_error`).textContent=''); const id=idEl.value, body=new URLSearchParams({ip_address:addressEl.value,name:nameEl.value,note:noteEl.value,expires_at:expiresEl.value,is_active:activeEl.checked?'1':'0'}); if(id) body.append('_method','PUT'); const res=await fetch(id?apiIpUrls.update.replace(':id',id):apiIpUrls.store,{method:'POST',headers:{'X-CSRF-TOKEN':apiIpCsrf,'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body}); const json=await res.json().catch(()=>({})); if(!res.ok){ Object.entries(json.errors||{}).forEach(([key, errors])=>{const el=document.getElementById(`api_ip_${key}_error`);if(el)el.textContent=errors[0];}); if(!json.errors) Swal?.fire('Error',json.message||'Gagal menyimpan','error'); return; } modal.hide(); table.ajax.reload(); Swal?.fire('Berhasil',json.message,'success'); });
    tableEl.on('click','.api-ip-disable',async function(e){e.preventDefault(); if(!confirm('Nonaktifkan IP ini? Akses API dari IP tersebut akan langsung ditolak.'))return; const res=await fetch(apiIpUrls.destroy.replace(':id',this.dataset.id),{method:'POST',headers:{'X-CSRF-TOKEN':apiIpCsrf,'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:'_method=DELETE'}); const json=await res.json().catch(()=>({})); if(!res.ok){Swal?.fire('Error',json.message||'Gagal menonaktifkan','error');return;} table.ajax.reload();Swal?.fire('Berhasil',json.message,'success');});
});
</script>
@endpush
