@extends('layouts.admin')

@section('title', 'Satuan (UOM)')
@section('page_title', 'Satuan (UOM)')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.unit-of-measures.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.unit-of-measures.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.unit-of-measures.index', 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><input class="form-control form-control-solid w-250px" id="uom_search" placeholder="Cari kode atau nama satuan"></div>
        <div class="card-toolbar">
            @if($canCreate)<button class="btn btn-primary" id="uom_create" data-bs-toggle="modal" data-bs-target="#uom_modal">Tambah Satuan</button>@endif
        </div>
    </div>
    <div class="card-body py-6"><div class="table-responsive">
        <table class="table align-middle table-row-dashed fs-6 gy-5" id="uom_table">
            <thead><tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase"><th>Kode</th><th>Nama</th><th class="text-end">Aksi</th></tr></thead><tbody></tbody>
        </table>
    </div></div>
</div>

<div class="modal fade" id="uom_modal" tabindex="-1"><div class="modal-dialog modal-dialog-centered mw-500px"><div class="modal-content">
    <div class="modal-header"><h2 class="fw-bolder" id="uom_title">Tambah Satuan</h2><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="uom_form"><input type="hidden" id="uom_id"><div class="mb-5"><label class="required form-label">Kode</label><input class="form-control" id="uom_code" maxlength="30" placeholder="contoh: pcs" required><div class="invalid-feedback d-block" id="uom_code_error"></div></div><div class="mb-5"><label class="required form-label">Nama</label><input class="form-control" id="uom_name" maxlength="100" placeholder="contoh: Pieces" required><div class="invalid-feedback d-block" id="uom_name_error"></div></div><div class="text-end"><button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div></form></div>
</div></div></div>
@endsection

@push('scripts')
<script>
const uomUrls = { data: '{{ route('admin.masterdata.unit-of-measures.data') }}', store: '{{ route('admin.masterdata.unit-of-measures.store') }}', update: '{{ route('admin.masterdata.unit-of-measures.update', ':id') }}', destroy: '{{ route('admin.masterdata.unit-of-measures.destroy', ':id') }}' };
const uomCsrf = '{{ csrf_token() }}';
const uomPermissions = { update: {{ $canUpdate ? 'true' : 'false' }}, delete: {{ $canDelete ? 'true' : 'false' }} };
document.addEventListener('DOMContentLoaded', () => {
    const tableEl = $('#uom_table'), search = document.getElementById('uom_search'), form = document.getElementById('uom_form'), modal = new bootstrap.Modal(document.getElementById('uom_modal'));
    const idEl = document.getElementById('uom_id'), codeEl = document.getElementById('uom_code'), nameEl = document.getElementById('uom_name'), title = document.getElementById('uom_title');
    const table = tableEl.DataTable({ processing:true, serverSide:true, dom:'rtip', ajax:{url:uomUrls.data,dataSrc:'data',data:p=>p.q=search?.value||''}, columns:[
        {data:'code'}, {data:'name'}, {data:'id',className:'text-end',orderable:false,searchable:false,render:(id,t,row)=> {
            const edit = uomPermissions.update ? `<a href="#" class="menu-link px-3 uom-edit" data-id="${id}" data-code="${row.code}" data-name="${row.name}">Edit</a>` : '';
            const del = uomPermissions.delete ? `<a href="#" class="menu-link px-3 text-danger uom-delete" data-id="${id}">Hapus</a>` : '';
            return (edit||del) ? `<div class="menu-item px-3">${edit}</div><div class="menu-item px-3">${del}</div>` : '';
        }}]});
    search?.addEventListener('keyup',()=>table.ajax.reload());
    document.getElementById('uom_create')?.addEventListener('click',()=>{ form.reset(); idEl.value=''; title.textContent='Tambah Satuan'; });
    tableEl.on('click','.uom-edit',function(e){ e.preventDefault(); idEl.value=this.dataset.id; codeEl.value=this.dataset.code; nameEl.value=this.dataset.name; title.textContent='Edit Satuan'; modal.show(); });
    form.addEventListener('submit',async e=>{ e.preventDefault(); ['uom_code_error','uom_name_error'].forEach(x=>document.getElementById(x).textContent=''); const id=idEl.value; const body=new URLSearchParams({code:codeEl.value,name:nameEl.value}); if(id) body.append('_method','PUT'); const res=await fetch(id?uomUrls.update.replace(':id',id):uomUrls.store,{method:'POST',headers:{'X-CSRF-TOKEN':uomCsrf,'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body}); const json=await res.json().catch(()=>({})); if(!res.ok){ document.getElementById('uom_code_error').textContent=json.errors?.code?.[0]||''; document.getElementById('uom_name_error').textContent=json.errors?.name?.[0]||json.message||'Gagal menyimpan'; return; } modal.hide(); table.ajax.reload(); Swal?.fire('Berhasil',json.message,'success'); });
    tableEl.on('click','.uom-delete',async function(e){e.preventDefault(); if(!confirm('Hapus satuan ini?'))return; const res=await fetch(uomUrls.destroy.replace(':id',this.dataset.id),{method:'POST',headers:{'X-CSRF-TOKEN':uomCsrf,'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:'_method=DELETE'}); const json=await res.json().catch(()=>({})); if(!res.ok){Swal?.fire('Error',json.message||'Gagal menghapus','error');return;} table.ajax.reload();Swal?.fire('Berhasil',json.message,'success');});
});
</script>
@endpush
