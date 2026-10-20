{{--
    Dropdown filter toko / channel untuk laporan berbasis resi.
    Param: $tokos (Collection id,name), $id (default filter_toko), $class (lebar), $type ('toko'|'channel').
--}}
@php
    $filterType = $type ?? 'toko';
    $filterLabel = $filterType === 'channel' ? 'Channel' : 'Toko';
@endphp
<select id="{{ $id ?? 'filter_'.$filterType }}" class="form-select form-select-solid {{ $class ?? 'w-175px' }}" aria-label="Filter {{ $filterLabel }}">
    <option value="">Semua {{ $filterLabel }}</option>
    @foreach(($tokos ?? collect()) as $option)
        <option value="{{ $option->id }}">{{ $option->name }}</option>
    @endforeach
    <option value="none">Tanpa {{ $filterLabel }}</option>
</select>
