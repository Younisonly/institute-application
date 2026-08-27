@extends('prints.layout')

@section('title', __('general.account_statement'))

@section('content')
    <h2 class="title">{{ __('general.account_statement') }} — {{ $statement['account']->code }} {{ $statement['account']->name }}</h2>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.opening_balance') }}</span>
            <span class="value">{{ number_format($statement['opening']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.total_debit') }}</span>
            <span class="value">{{ number_format($statement['totalDebit']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.total_credit') }}</span>
            <span class="value">{{ number_format($statement['totalCredit']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.closing_balance') }}</span>
            <span class="value amount-success">{{ \App\Helpers\MoneyFormatter::formatAccountBalance((float) $statement['closing'], $statement['account']->type) }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('general.date') }}</th>
                <th>{{ __('general.entry_no') }}</th>
                <th>{{ __('general.description') }}</th>
                <th>{{ __('general.document') }}</th>
                <th>{{ __('general.counterparty') }}</th>
                <th>{{ __('general.party') }}</th>
                <th>{{ __('general.debit') }}</th>
                <th>{{ __('general.credit') }}</th>
                <th>{{ __('general.balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement['rows'] as $row)
                <tr>
                    <td>{{ $row['entry']->date->format('d/m/Y') }}</td>
                    <td>#{{ $row['entry']->entry_no }}</td>
                    <td>{{ $row['entry']->description ?? '—' }}</td>
                    <td>{{ $row['entry']->document_type ? class_basename($row['entry']->document_type) : '—' }}</td>
                    <td>{{ $statement['counterparties'][$row['line_id']] ?? '—' }}</td>
                    <td>{{ $row['party'] }}</td>
                    <td class="{{ (float) $row['debit'] > 0 ? 'amount-danger' : '' }}">{{ number_format((float) $row['debit']) }}</td>
                    <td class="{{ (float) $row['credit'] > 0 ? 'amount-success' : '' }}">{{ number_format((float) $row['credit']) }}</td>
                    <td>{{ \App\Helpers\MoneyFormatter::formatAccountBalance((float) $row['balance'], $statement['account']->type) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="empty">{{ __('general.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection