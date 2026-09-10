@extends('prints.layout')

@section('title', __('general.teaching_sessions_report'))

@section('content')
    <h2 class="title">{{ __('general.teaching_sessions_report') }}</h2>

    <div class="info-grid">
        <div class="item"><span class="label">{{ __('general.batch') }}</span><span class="value">{{ $batch->name }}</span></div>
        <div class="item"><span class="label">{{ __('general.course') }}</span><span class="value">{{ $batch->course?->name ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.primary_teacher') }}</span><span class="value">{{ $batch->teacher?->name ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.start_date') }}</span><span class="value">{{ $batch->start_date?->format('d/m/Y') ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.end_date') }}</span><span class="value">{{ $batch->end_date?->format('d/m/Y') ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.total_sessions_count') }}</span><span class="value">{{ $sessions->count() }}</span></div>
        <div class="item"><span class="label">{{ __('general.total_hours_worked') }}</span><span class="value">{{ number_format((float) $sessions->sum('actual_hours'), 2) }} {{ __('general.hour') }}</span></div>
        <div class="item"><span class="label">{{ __('general.status') }}</span><span class="value">{{ __("general.status_{$batch->status}") }}</span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th style="width:130px">{{ __('general.date') }}</th>
                <th>{{ __('general.actual_teacher') }}</th>
                <th style="width:100px">{{ __('general.status') }}</th>
                <th style="width:90px">{{ __('general.actual_hours') }}</th>
                <th>{{ __('general.notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $index => $session)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($session->date)->translatedFormat('l d/m/Y') }}</td>
                    <td>
                        {{ $session->actualTeacher?->name ?? '—' }}
                        @if ($session->primary_teacher_id && $session->actual_teacher_id && (int) $session->primary_teacher_id !== (int) $session->actual_teacher_id)
                            <span style="font-size:10px; color:#0284c7; margin-right:4px;">({{ __('general.substitute') }})</span>
                        @endif
                    </td>
                    <td>{{ __("general.status_{$session->status}") }}</td>
                    <td>{{ number_format((float) $session->actual_hours, 2) }}</td>
                    <td>
                        @if ($session->status === 'cancelled' && $session->cancellation_reason)
                            <span style="color:#dc2626;">{{ $session->cancellation_reason }}</span>
                        @else
                            {{ $session->notes ?? '—' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center">{{ __('general.no_records_found') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
