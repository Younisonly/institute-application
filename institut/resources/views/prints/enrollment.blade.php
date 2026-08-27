@extends('prints.layout')

@section('title', __('general.enrollment_report'))

@section('content')
    <h2 class="title">{{ __('general.enrollment_report') }}</h2>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.total') }}</span>
            <span class="value">{{ $report['total'] }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.active') }}</span>
            <span class="value">{{ $report['active'] }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.suspended') }}</span>
            <span class="value">{{ $report['suspended'] }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.closed') }}</span>
            <span class="value">{{ $report['closed'] }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.transfer') }}</span>
            <span class="value">{{ $report['transferred'] }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('general.month') }}</th>
                <th>{{ __('general.student') }}</th>
                <th>{{ __('general.course') }}</th>
                <th>{{ __('general.batch') }}</th>
                <th>{{ __('general.period') }}</th>
                <th>{{ __('general.status') }}</th>
                <th style="text-align:right">{{ __('general.fees') }}</th>
                <th style="text-align:right">{{ __('general.discount_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $i => $registration)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $registration->start_month }}</td>
                    <td>{{ $registration->student?->name ?? '—' }}</td>
                    <td>{{ $registration->course?->name ?? '—' }}</td>
                    <td>{{ $registration->batch?->name ?? '—' }}</td>
                    <td>{{ $registration->batch?->periods_label ?? '—' }}</td>
                    <td>
                        <span class="badge {{ match ($registration->status) {
                            'active' => 'success',
                            'suspended' => 'warning',
                            'closed' => 'danger',
                            default => 'info',
                        } }}">{{ __("general.{$registration->status}") }}</span>
                    </td>
                    <td style="text-align:right">{{ number_format((float) $registration->price_snapshot) }}</td>
                    <td style="text-align:right">{{ (float) $registration->discount_amount > 0 ? number_format((float) $registration->discount_amount) : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="empty">{{ __('general.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
