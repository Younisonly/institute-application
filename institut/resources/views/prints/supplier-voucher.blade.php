@extends('prints.layout')

@section('title', __('general.payment').' #'.$transaction->receipt_no)

@section('content')
    <h2 class="title">{{ __('general.supplier_payment') }} — {{ $transaction->receipt_no }}</h2>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.supplier') }}</span>
            <span class="value">{{ $transaction->supplier->name }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.receipt_no') }}</span>
            <span class="value">#{{ $transaction->receipt_no }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.date') }}</span>
            <span class="value">{{ $transaction->date->translatedFormat('d F Y') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.payment_method') }}</span>
            <span class="value">{{ __("general.method_{$transaction->method}") }}</span>
        </div>
        @if ($transaction->transaction_ref)
            <div class="item">
                <span class="label">{{ __('general.transaction_ref') }}</span>
                <span class="value">{{ $transaction->transaction_ref }}</span>
            </div>
        @endif
        @if ($transaction->description)
            <div class="item" style="grid-column: 1 / -1;">
                <span class="label">{{ __('general.description') }}</span>
                <span class="value">{{ $transaction->description }}</span>
            </div>
        @endif
    </div>

    <div class="totals">
        <div class="total-line grand">
            <span>{{ __('general.amount') }}</span>
            <span>{{ number_format((float) $transaction->amount) }} {{ __('general.currency') }}</span>
        </div>
        <div style="font-size:12px; text-align:center; color:#64748b; margin-top:4px; direction:rtl;">
            {{ app(\App\Services\MoneyWordsService::class)->toArabicRials((float) $transaction->amount) }}
        </div>
    </div>
@endsection
