@extends('prints.layout')

@section('title', __('general.receipt').' #'.$transaction->receipt_no)

@section('content')
    <div class="receipt-head" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div class="receipt-number">
            <span class="receipt-label">{{ __('general.receipt') }}</span>
            <span class="receipt-value">#{{ $transaction->receipt_no }}</span>
        </div>
        <div class="qr-code">
            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(80)->generate(route('receipts.print', $transaction)) !!}
        </div>
    </div>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.student') }}</span>
            <span class="value">{{ $transaction->student->name }}</span>
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
        @if ($transaction->registration?->course)
            <div class="item">
                <span class="label">{{ __('general.course') }}</span>
                <span class="value">{{ $transaction->registration->course->name }}</span>
            </div>
            <div class="item">
                <span class="label">{{ __('general.period') }}</span>
                <span class="value">{{ $transaction->registration->batch?->periods_label ?? '—' }}</span>
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
        @if ($balance !== null)
            @if ($balance > 0)
                <div class="total-line" style="margin-top:12px; border-top:1px dashed #e2e8f0; padding-top:8px;">
                    <span style="color:#dc2626; font-weight:600;">{{ __('general.remaining_on_you') }}</span>
                    <span style="color:#dc2626; font-weight:700;">{{ number_format($balance) }} {{ __('general.currency') }}</span>
                </div>
            @elseif ($balance < 0)
                <div class="total-line" style="margin-top:12px; border-top:1px dashed #e2e8f0; padding-top:8px;">
                    <span style="color:#16a34a; font-weight:600;">{{ __('general.credit_for_you') }}</span>
                    <span style="color:#16a34a; font-weight:700;">{{ number_format(abs($balance)) }} {{ __('general.currency') }}</span>
                </div>
            @else
                <div class="total-line" style="margin-top:12px; border-top:1px dashed #e2e8f0; padding-top:8px;">
                    <span style="color:#16a34a; font-weight:600;">{{ __('general.balance_settled') }}</span>
                    <span style="color:#16a34a;">✓</span>
                </div>
            @endif
        @endif
    </div>

    <div class="signature-row">
        <div class="signature">
            <span class="signature-label">{{ __('general.received_by') }}</span>
            <span class="signature-line"></span>
        </div>
        <div class="signature">
            <span class="signature-label">{{ __('general.stamp') }}</span>
            <span class="stamp-box"></span>
        </div>
    </div>
@endsection
