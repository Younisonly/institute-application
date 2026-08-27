@extends('prints.layout')

@section('title', __('general.salary_sheet').' — '.$report['month'])

@section('content')
    <h2 class="title">{{ __('general.salary_sheet') }} — {{ $report['month'] }}</h2>

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.fees') }}</span>
            <span class="value">{{ number_format($report['collected']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.total') }}</span>
            <span class="value amount-danger">{{ number_format($report['total']) }} {{ __('general.currency') }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('general.staff_member') }}</th>
                <th>{{ __('general.job_title') }}</th>
                <th>{{ __('general.salary_type') }}</th>
                <th>{{ __('general.amount') }}</th>
                <th>{{ __('general.status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['staff']->name }}</td>
                    <td>{{ $row['staff']->jobTitle?->name ?? '—' }}</td>
                    <td>
                        @if ($row['staff']->salary_type === 'percentage')
                            {{ __('general.percentage') }} {{ (float) $row['staff']->percentage_value }}%
                        @else
                            {{ __("general.{$row['staff']->salary_type}") }}
                        @endif
                    </td>
                    <td class="{{ $row['staff']->salary_type === 'per_hour' ? '' : 'amount-danger' }}">
                        @if ($row['staff']->salary_type === 'per_hour')
                            {{ __('general.hours') }}
                        @else
                            {{ number_format($row['amount']) }}
                        @endif
                    </td>
                    <td>
                        @if ($row['paid'])
                            <span class="badge success">{{ __('general.paid_this_month') }}</span>
                        @else
                            <span class="badge gray">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">{{ __('general.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p style="font-size:11px;color:#94a3b8;margin-top:8px">{{ __('general.per_hour_hint') }}</p>
@endsection
