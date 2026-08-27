@extends('prints.layout')

@section('title', __('general.profit_report'))

@section('content')
    <h2 class="title">{{ __('general.profit_report') }} — {{ $report['month'] }}</h2>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.period') }}</span>
            <span class="value">{{ $report['from'] }} → {{ $report['to'] }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.income') }}</span>
            <span class="value amount-success">{{ number_format($report['income']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.expense') }}</span>
            <span class="value amount-danger">{{ number_format($report['spent']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.profit') }}</span>
            <span class="value {{ $report['net'] >= 0 ? 'amount-success' : 'amount-danger' }}">{{ number_format($report['net']) }} {{ __('general.currency') }}</span>
        </div>
    </div>

    <h2 class="title" style="font-size:13px;">{{ __('general.income') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('general.account') }}</th>
                <th>{{ __('general.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows']->where('type', 'income') as $row)
                <tr>
                    <td>{{ $row['account']->name }}</td>
                    <td class="amount-success">{{ number_format((float) $row['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="empty">{{ __('general.no_data') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="title" style="font-size:13px;">{{ __('general.expenses') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('general.category') }}</th>
                <th>{{ __('general.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows']->where('type', 'expense') as $row)
                <tr>
                    <td>{{ $row['account']->name }}</td>
                    <td class="amount-danger">{{ number_format((float) $row['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="empty">{{ __('general.no_data') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div class="total-line">
            <span>{{ __('general.income') }}</span>
            <span class="amount-success">{{ number_format($report['income']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="total-line">
            <span>{{ __('general.expense') }}</span>
            <span class="amount-danger">-{{ number_format($report['spent']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="total-line grand">
            <span>{{ __('general.net') }}</span>
            <span class="{{ $report['net'] >= 0 ? 'amount-success' : 'amount-danger' }}">{{ number_format($report['net']) }} {{ __('general.currency') }}</span>
        </div>
    </div>
@endsection