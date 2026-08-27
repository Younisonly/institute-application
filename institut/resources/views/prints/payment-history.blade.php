@extends('prints.layout')

@section('title', __('general.payment_history_report'))

@section('content')
    <h2 class="title">{{ __('general.payment_history_report') }}</h2>
    <div style="text-align:center;color:#64748b;margin-bottom:10px">
        {{ __('general.from') }}: <b>{{ $from }}</b> — {{ __('general.to') }}: <b>{{ $to }}</b>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('general.date') }}</th>
                <th>{{ __('general.student') }}</th>
                <th>{{ __('general.course') }}</th>
                <th>{{ __('general.receipt_no') }}</th>
                <th>{{ __('general.method') }}</th>
                <th style="text-align:right">{{ __('general.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $transaction)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $transaction->date->format('d/m/Y') }}</td>
                    <td>{{ $transaction->student?->name ?? '—' }}</td>
                    <td>{{ $transaction->registration?->course?->name ?? '—' }}</td>
                    <td>{{ $transaction->receipt_no ?? '—' }}</td>
                    <td>{{ __("general.method_{$transaction->method}") }}</td>
                    <td style="text-align:right">{{ number_format((float) $transaction->amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">{{ __('general.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div class="total-line grand">
            <span>{{ __('general.total') }}</span>
            <span class="amount-danger">{{ number_format($total) }} {{ __('general.currency') }}</span>
        </div>
    </div>
@endsection
