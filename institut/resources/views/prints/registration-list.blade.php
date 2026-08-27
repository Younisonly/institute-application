@extends('prints.layout')

@section('title', __('general.registration_lists'))

@section('content')
    <h2 class="title">{{ __('general.registration_lists') }}</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('general.student') }}</th>
                <th>{{ __('general.phone') }}</th>
                <th>{{ __('general.course') }}</th>
                <th>{{ __('general.batch') }}</th>
                <th>{{ __('general.period') }}</th>
                <th>{{ __('general.start_month') }}</th>
                <th>{{ __('general.end_month') }}</th>
                <th>{{ __('general.status') }}</th>
                <th style="text-align:right">{{ __('general.fees') }}</th>
                <th style="text-align:right">{{ __('general.discount_amount') }}</th>
                <th>{{ __('general.remaining_on_you') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $registration)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $registration->student->name }}</td>
                    <td>{{ $registration->student->phone ?? '—' }}</td>
                    <td>{{ $registration->course->name }}</td>
                    <td>{{ $registration->batch?->name ?? '—' }}</td>
                    <td>{{ $registration->batch?->periods_label ?? '—' }}</td>
                    <td>{{ $registration->start_month }}</td>
                    <td>{{ $registration->expected_end }}</td>
                    <td>
                        <span class="badge {{ match ($registration->status) {
                            'active' => 'success', 'suspended' => 'warning', 'closed' => 'danger', default => 'info',
                        } }}">{{ __("general.{$registration->status}") }}</span>
                    </td>
                    <td style="text-align:right">{{ number_format((float) $registration->price_snapshot) }}</td>
                    <td style="text-align:right">{{ (float) $registration->discount_amount > 0 ? number_format((float) $registration->discount_amount) : '—' }}</td>
                    <td class="{{ $registration->balance > 0 ? 'amount-danger' : 'amount-success' }}">{{ number_format($registration->balance) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="empty">{{ __('general.no_data') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div class="total-line">
            <span>{{ __('general.total') }}</span>
            <span>{{ count($rows) }} {{ __('general.registrations') }}</span>
        </div>
        <div class="total-line grand">
            <span>{{ __('general.remaining_on_you') }}</span>
            <span class="amount-danger">{{ number_format($totalBalance) }} {{ __('general.currency') }}</span>
        </div>
    </div>
@endsection
