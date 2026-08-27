@extends('prints.layout')

@section('title', __('general.stock_inventory_report'))

@section('content')
    <h2 class="title">{{ __('general.stock_inventory_report') }}</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('general.name') }}</th>
                <th>{{ __('general.type') }}</th>
                <th>{{ __('general.category') }}</th>
                <th style="text-align:right">{{ __('general.stock') }}</th>
                <th style="text-align:right">{{ __('general.buy_price') }}</th>
                <th style="text-align:right">{{ __('general.sale_price') }}</th>
                <th style="text-align:right">{{ __('general.stock_value') }}</th>
                <th>{{ __('general.low_stock') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->name }}</td>
                    <td>
                        @if ($row->type === 'book')
                            <span class="badge info">{{ __('general.book') }}</span>
                        @else
                            <span class="badge warning">{{ __('general.item') }}</span>
                        @endif
                    </td>
                    <td>{{ $row->category ?? '—' }}</td>
                    <td style="text-align:right">{{ $row->stock }}</td>
                    <td style="text-align:right">{{ number_format($row->buy_price) }}</td>
                    <td style="text-align:right">{{ number_format($row->sale_price) }}</td>
                    <td style="text-align:right">{{ number_format($row->buy_value) }}</td>
                    <td>
                        @if ($row->low_stock)
                            <span class="badge danger">{{ __('general.yes') }}</span>
                        @else
                            <span class="badge success">{{ __('general.no') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="empty">{{ __('general.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div class="total-line grand">
            <span>{{ __('general.total') }} ({{ __('general.stock_value') }})</span>
            <span class="amount-danger">{{ number_format($totalValue) }} {{ __('general.currency') }}</span>
        </div>
    </div>
@endsection
