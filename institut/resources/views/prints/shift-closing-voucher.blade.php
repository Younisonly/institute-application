@extends('prints.layout')

@section('title', __('general.shift_closing_voucher').' — '.$shift->shift_no)

@section('content')
    <h2 class="title">{{ __('general.shift_closing_voucher') }} — {{ $shift->shift_no }}</h2>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.cashbox') }}</span>
            <span class="value">{{ $shift->cashbox->name }} ({{ $shift->cashbox->code }})</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.keeper') }}</span>
            <span class="value">{{ $shift->user->name }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.opened_at') }}</span>
            <span class="value">{{ $shift->opened_at->format('d/m/Y H:i') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.closed_at') }}</span>
            <span class="value">{{ $shift->closed_at ? $shift->closed_at->format('d/m/Y H:i') : '—' }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.opening_balance') }}</span>
            <span class="value">{{ number_format($shift->opening_balance, 2) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.system_cash_in') }}</span>
            <span class="value">{{ number_format($shift->system_cash_in, 2) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.system_cash_out') }}</span>
            <span class="value">{{ number_format($shift->system_cash_out, 2) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.expected_closing_balance') }}</span>
            <span class="value">{{ number_format($shift->expected_closing_balance, 2) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.physical_cash_count') }}</span>
            <span class="value" style="font-weight:bold;">{{ number_format($shift->physical_cash_count ?? 0, 2) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.variance_amount') }}</span>
            <span class="value" style="font-weight:bold; color: {{ $shift->variance_amount > 0 ? '#16a34a' : ($shift->variance_amount < 0 ? '#dc2626' : '#2563eb') }};">
                {{ number_format($shift->variance_amount, 2) }} {{ __('general.currency') }} ({{ __('general.variance_' . $shift->variance_type) }})
            </span>
        </div>
        @if ($shift->variance_notes)
            <div class="item" style="grid-column: 1 / -1;">
                <span class="label">{{ __('general.variance_notes') }}</span>
                <span class="value">{{ $shift->variance_notes }}</span>
            </div>
        @endif
    </div>

    <div class="totals">
        <div class="total-line grand">
            <span>{{ __('general.physical_cash_count') }}</span>
            <span>{{ number_format((float) ($shift->physical_cash_count ?? 0), 2) }} {{ __('general.currency') }}</span>
        </div>
        <div style="font-size:12px; text-align:center; color:#64748b; margin-top:4px; direction:rtl;">
            {{ app(\App\Services\MoneyWordsService::class)->toArabicRials((float) ($shift->physical_cash_count ?? 0)) }}
        </div>
    </div>
@endsection
