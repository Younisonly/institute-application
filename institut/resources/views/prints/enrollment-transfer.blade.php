@extends('prints.layout')

@section('title', __('general.transfer_document'))

@section('content')
    <style>
        .transfer-title-ar { font-size: 22px; font-weight: 800; color: #0f172a; }
        .transfer-title-en { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1.5px; margin: 4px 0 16px; }
        .transfer-no { font-size: 13px; color: #334155; font-weight: 700; }
        .arrow { font-size: 18px; font-weight: 800; color: #00B7EB; padding: 0 6px; }
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 18px 0; }
        .summary-box { border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; background: #f8fafc; }
        .summary-label { font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px; }
        .summary-value { font-size: 15px; font-weight: 800; color: #0f172a; }
    </style>

    <div style="text-align: center;">
        <div class="transfer-title-ar">{{ __('general.transfer_document') }}</div>
        <div class="transfer-title-en">{{ __('general.transfer_document') }}</div>
        <div class="transfer-no">{{ __('general.transfer_record_no', ['no' => '#'.$transfer->id]) }}</div>
    </div>

    <div class="info-grid" style="margin-top: 14px;">
        <div class="item">
            <span class="label">{{ __('general.student') }}</span>
            <span class="value">{{ $transfer->student->name }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.transferred_at') }}</span>
            <span class="value">{{ $transfer->transferred_at->format('d/m/Y H:i') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.transfer_from') }}</span>
            <span class="value">{{ $transfer->fromCourse->name }}{{ $transfer->fromBatch ? ' — '.$transfer->fromBatch->name : '' }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.transfer_to') }}</span>
            <span class="value">{{ $transfer->toCourse->name }}{{ $transfer->toBatch ? ' — '.$transfer->toBatch->name : '' }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.reason') }}</span>
            <span class="value">{{ $transfer->reason }}</span>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-box">
            <div class="summary-label">{{ __('general.balance_carried') }}</div>
            <div class="summary-value">{{ number_format((float) $transfer->balance_carried) }} {{ __('general.currency') }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">{{ __('general.months_carried') }}</div>
            <div class="summary-value">{{ $transfer->months_carried }} {{ __('general.month') }}</div>
        </div>
    </div>

    <table style="margin-top: 6px;">
        <thead>
            <tr>
                <th>{{ __('general.item') }}</th>
                <th>{{ __('general.balance_carried') }}</th>
                <th>{{ __('general.months_carried') }}</th>
                <th>{{ __('general.books_supplies') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('general.transfer_assets_line') }}</td>
                <td>{{ number_format((float) $transfer->balance_carried) }} {{ __('general.currency') }}</td>
                <td>{{ $transfer->months_carried }}</td>
                <td>{{ $transfer->carry_items ? __('general.yes') : __('general.no') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-row" style="margin-top: 56px;">
        <div class="signature">
            <span class="signature-label">{{ __('general.student_signature') }}</span>
            <div class="signature-line"></div>
        </div>
        <div class="signature">
            <span class="signature-label">{{ __('general.registrar_signature') }}</span>
            <div class="signature-line"></div>
        </div>
        <div class="signature">
            <span class="signature-label">{{ __('general.dean_signature') }}</span>
            <div class="signature-line"></div>
        </div>
    </div>

    @if ($transfer->transferredBy || $transfer->approvedBy)
        <div class="info-grid" style="margin-top: 24px;">
            @if ($transfer->transferredBy)
                <div class="item">
                    <span class="label">{{ __('general.transferred_by') }}</span>
                    <span class="value">{{ $transfer->transferredBy->name }}</span>
                </div>
            @endif
            @if ($transfer->approvedBy)
                <div class="item">
                    <span class="label">{{ __('general.approved_by') }}</span>
                    <span class="value">{{ $transfer->approvedBy->name }}</span>
                </div>
            @endif
        </div>
    @endif
@endsection
