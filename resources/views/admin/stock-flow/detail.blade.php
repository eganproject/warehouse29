@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <div class="fw-bolder fs-5">{{ $transaction->code }}</div>
                <div class="text-muted fs-7">{{ $transaction->transacted_at?->format('Y-m-d H:i') }}</div>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row mb-6">
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Ref No</div>
                <div>{{ $transaction->ref_no ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Catatan</div>
                <div>{{ $transaction->note ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Total Qty</div>
                <div>{{ $totalQty }}</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaction->items as $row)
                        <tr>
                            <td>{{ $row->item?->sku }} - {{ $row->item?->name }}</td>
                            <td>{{ $row->qty }}</td>
                            <td>{{ $row->note ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
