@extends('prints.layout')

@section('title', __('general.statement').' — '.$student->name)

@section('content')
    <h2 class="title">{{ __('general.statement') }}</h2>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.student') }}</span>
            <span class="value">{{ $student->name }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.phone') }}</span>
            <span class="value">{{ $student->phone ?? '—' }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.courses') }}</span>
            <span class="value">
                {{ $student->registrations->map(fn ($r) => $r->course->name.' ('.$r->start_month.')')->implode('، ') ?: '—' }}
            </span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.date') }}</span>
            <span class="value">{{ now()->translatedFormat('d F Y') }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('general.date') }}</th>
                <th>{{ __('general.type') }}</th>
                <th>{{ __('general.description') }}</th>
                <th>{{ __('general.receipt_no') }}</th>
                <th>{{ __('general.amount') }}</th>
                <th>{{ __('general.balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php($t = $row['transaction'])
                <tr @if ($t->voided_at !== null) style="text-decoration: line-through; color: #94a3b8;" @endif>
                    <td>{{ $t->date->format('d/m/Y') }}</td>
                    <td>
                        <span class="badge {{ match ($t->type) { 'charge' => 'danger', 'payment' => 'success', default => 'warning' } }}">
                            {{ __("general.{$t->type}") }}
                        </span>
                        @if ($t->voided_at !== null)
                            <span class="badge gray">{{ __('general.voided') }}</span>
                        @endif
                    </td>
                    <td>{{ $t->description ?? '—' }}</td>
                    <td>{{ $t->receipt_no ? '#'.$t->receipt_no : '—' }}</td>
                    <td class="{{ in_array($t->type, ['payment','refund']) ? 'amount-success' : 'amount-danger' }}">
                        {{ in_array($t->type, ['payment','refund']) ? '-' : '+' }} {{ number_format((float) $t->amount) }}
                    </td>
                    <td class="{{ $row['running'] > 0 ? 'amount-danger' : ($row['running'] < 0 ? 'amount-success' : '') }}">
                        {{ number_format(abs($row['running'])) }} {{ __('general.currency') }}
                        <small style="display:block;font-size:10px;opacity:.75;">
                            {{ $row['running'] > 0 ? __('general.remaining_on_you') : ($row['running'] < 0 ? __('general.credit_for_you') : __('general.balance_settled')) }}
                        </small>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">{{ __('general.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        @if ($balance > 0)
            <div class="total-line grand">
                <span>{{ __('general.remaining_on_you') }}</span>
                <span class="amount-danger">{{ number_format($balance) }} {{ __('general.currency') }}</span>
            </div>
        @elseif ($balance < 0)
            <div class="total-line grand">
                <span>{{ __('general.credit_for_you') }}</span>
                <span class="amount-success">{{ number_format(abs($balance)) }} {{ __('general.currency') }}</span>
            </div>
        @else
            <div class="total-line grand">
                <span>{{ __('general.balance_settled') }}</span>
                <span class="amount-success">—</span>
            </div>
        @endif
    </div>
@endsection
