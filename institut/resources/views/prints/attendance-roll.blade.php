@extends('prints.layout')

@section('title', __('general.attendance_roll'))

@section('content')
    <h2 class="title">{{ __('general.attendance_roll') }}</h2>

    <div class="info-grid">
        <div class="item"><span class="label">{{ __('general.batch') }}</span><span class="value">{{ $session->batch?->name ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.course') }}</span><span class="value">{{ $session->batch?->course?->name ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.date') }}</span><span class="value">{{ $session->date?->format('d/m/Y') }}</span></div>
        <div class="item"><span class="label">{{ __('general.period') }}</span><span class="value">{{ $session->period?->name ?? $session->batch?->periods_label ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.recorded_by') }}</span><span class="value">{{ $session->creator?->name ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.students_count') }}</span><span class="value">{{ $session->records->count() }}</span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th>{{ __('general.student') }}</th>
                <th style="width:100px">{{ __('general.status') }}</th>
                <th>{{ __('general.notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($session->records as $index => $record)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $record->registration?->student?->name ?? '—' }}</td>
                    <td>{{ __("general.{$record->status}") }}</td>
                    <td>{{ $record->note ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center">{{ __('general.no_records_found') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
