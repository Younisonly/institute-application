@extends('prints.layout')

@section('title', __('general.receipt').' #'.$transaction->receipt_no)

@section('content')
    <h2 class="title">{{ $transaction->type === 'in' ? __('general.income') : __('general.expense') }} — {{ $transaction->receipt_no }}</h2>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.party') }}</span>
            <span class="value">{{ $transaction->person->name }}</span>
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
        @if ($transaction->incomeCategory)
            <div class="item">
                <span class="label">{{ __('general.income_category') }}</span>
                <span class="value">{{ $transaction->incomeCategory->name }}</span>
            </div>
        @endif
        @if ($transaction->expenseCategory)
            <div class="item">
                <span class="label">{{ __('general.expense_category') }}</span>
                <span class="value">{{ $transaction->expenseCategory->name }}</span>
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
