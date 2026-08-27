@extends('prints.layout')

@section('title', __('general.daily_cash_report'))

@section('content')
    <h2 class="title">{{ __('general.daily_cash_report') }} — {{ \Carbon\CarbonImmutable::parse($report['date'])->translatedFormat('d F Y') }}</h2>

    <table>
        <thead>
            <tr>
                <th>{{ __('general.entry_no') }}</th>
                <th>{{ __('general.type') }}</th>
                <th>{{ __('general.party') }}</th>
                <th>{{ __('general.description') }}</th>
                <th>{{ __('general.in') }}</th>
                <th>{{ __('general.out') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['entries'] as $row)
                @php
                    $doc = $row['entry']->document;
                    $label = match (true) {
                        $doc instanceof \App\Models\StudentTransaction => $doc->type === 'refund' ? __('general.refund') : __('general.payment'),
                        $doc instanceof \App\Models\OtherPeopleTransaction => __("general.{$doc->type}"),
                        $doc instanceof \App\Models\StaffTransaction => __("general.{$doc->type}"),
                        $doc instanceof \App\Models\Expense => __('general.expense'),
                        $doc instanceof \App\Models\SupplierTransaction => __('general.supplier_payment'),
                        $doc instanceof \App\Models\Transfer => __('general.transfer'),
                        default => __('general.other'),
                    };
                @endphp
                <tr>
                    <td>#{{ $row['entry']->entry_no }}</td>
                    <td>{{ $label }}</td>
                    <td>{{ $row['entry']->lines->first(fn ($line) => $line->party_id !== null)?->party?->name ?? '—' }}</td>
                    <td>{{ $row['entry']->description ?? '—' }}</td>
                    @if ((float) $row['in'] > 0)
                        <td class="amount-success">{{ number_format((float) $row['in']) }}</td>
                        <td>—</td>
                    @else
                        <td>—</td>
                        <td class="amount-danger">{{ number_format((float) $row['out']) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">{{ __('general.no_data') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div class="total-line">
            <span>{{ __('general.collected') }}</span>
            <span class="amount-success">{{ number_format($report['collected']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="total-line">
            <span>{{ __('general.refunded') }}</span>
            <span class="amount-danger">-{{ number_format($report['refunded']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="total-line">
            <span>{{ __('general.spent') }}</span>
            <span class="amount-danger">-{{ number_format($report['spent']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="total-line grand">
            <span>{{ __('general.net') }}</span>
            <span class="{{ $report['net'] >= 0 ? 'amount-success' : 'amount-danger' }}">{{ number_format($report['net']) }} {{ __('general.currency') }}</span>
        </div>
    </div>
@endsection