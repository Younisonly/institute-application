@extends('prints.layout')

@section('title', __('general.teaching_sessions_report'))

@section('content')
    @php
        $totalSessions    = $sessions->count();
        $completedCount   = $sessions->where('status', 'completed')->count();
        $substitutedCount = $sessions->where('status', 'substituted')->count();
        $cancelledCount   = $sessions->where('status', 'cancelled')->count();
        $postponedCount   = $sessions->where('status', 'postponed')->count();
        $shortHourCount   = $sessions->filter(fn($s) => (float)$s->actual_hours > 0 && (float)$s->actual_hours < (float)$s->planned_hours)->count();

        $totalActualHours  = (float) $sessions->sum('actual_hours');
        $totalPlannedHours = (float) $sessions->sum('planned_hours');
        $completionRate    = $totalPlannedHours > 0
            ? min(100, round(($totalActualHours / $totalPlannedHours) * 100, 1))
            : ($totalSessions > 0 ? min(100, round((($completedCount + $substitutedCount) / $totalSessions) * 100, 1)) : 0);
        $substitutionPct   = ($completedCount + $substitutedCount) > 0
            ? round(($substitutedCount / max(1, $completedCount + $substitutedCount)) * 100, 1)
            : 0;
    @endphp

    {{-- Title --}}
    <h2 class="title">{{ __('general.teaching_sessions_report') }}</h2>

    {{-- Batch Info Grid --}}
    <div class="info-grid">
        <div class="item">
            <span class="label">{{ __('general.batch') }}</span>
            <span class="value">{{ $batch->name }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.course') }}</span>
            <span class="value">{{ $batch->course?->name ?? '—' }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.primary_teacher') }}</span>
            <span class="value">{{ $batch->teacher?->name ?? '—' }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.status') }}</span>
            <span class="value">{{ __("general.batch_status_{$batch->status}") }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.start_date') }}</span>
            <span class="value">{{ $batch->start_date?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.end_date') }}</span>
            <span class="value">{{ $batch->end_date?->format('d/m/Y') ?? '—' }}</span>
        </div>
        @if($batch->periods && $batch->periods->isNotEmpty())
        <div class="item">
            <span class="label">{{ __('general.period') }}</span>
            <span class="value">{{ $batch->periods->pluck('name')->join('، ') }}</span>
        </div>
        @endif
    </div>

    {{-- Summary Stats Table --}}
    <table style="margin-top: 14px; border-collapse: collapse; width: 100%;">
        <thead>
            <tr style="background: #f0f9ff;">
                <th style="border: 1px solid #bae6fd; padding: 7px 10px; font-weight: 800; color: #0369a1;">
                    {{ __('general.number_of_sessions') }}
                </th>
                <th style="border: 1px solid #bbf7d0; padding: 7px 10px; font-weight: 800; color: #15803d;">
                    {{ __('general.status_completed') }}
                </th>
                <th style="border: 1px solid #bae6fd; padding: 7px 10px; font-weight: 800; color: #0284c7;">
                    {{ __('general.status_substituted') }}
                </th>
                <th style="border: 1px solid #fecaca; padding: 7px 10px; font-weight: 800; color: #b91c1c;">
                    {{ __('general.status_cancelled') }}
                </th>
                <th style="border: 1px solid #fde68a; padding: 7px 10px; font-weight: 800; color: #b45309;">
                    {{ __('general.status_postponed') }}
                </th>
                <th style="border: 1px solid #e9d5ff; padding: 7px 10px; font-weight: 800; color: #6d28d9;">
                    {{ __('general.total_taught_hours') }}
                </th>
                <th style="border: 1px solid #e9d5ff; padding: 7px 10px; font-weight: 800; color: #6d28d9;">
                    {{ __('general.completion_rate') }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr style="text-align: center;">
                <td style="border: 1px solid #bae6fd; padding: 7px 10px; font-weight: 700; font-size: 15px;">{{ $totalSessions }}</td>
                <td style="border: 1px solid #bbf7d0; padding: 7px 10px; font-weight: 700; color: #16a34a;">{{ $completedCount }}</td>
                <td style="border: 1px solid #bae6fd; padding: 7px 10px; font-weight: 700; color: #0284c7;">
                    {{ $substitutedCount }}
                    @if($substitutionPct > 0)
                    <span style="font-size: 10px; color: #64748b;">({{ $substitutionPct }}%)</span>
                    @endif
                </td>
                <td style="border: 1px solid #fecaca; padding: 7px 10px; font-weight: 700; color: #b91c1c;">{{ $cancelledCount }}</td>
                <td style="border: 1px solid #fde68a; padding: 7px 10px; font-weight: 700; color: #b45309;">{{ $postponedCount }}</td>
                <td style="border: 1px solid #e9d5ff; padding: 7px 10px; font-weight: 700; color: #6d28d9;">
                    {{ number_format($totalActualHours, 1) }} / {{ number_format($totalPlannedHours, 1) }} {{ __('general.hours_short') }}
                </td>
                <td style="border: 1px solid #e9d5ff; padding: 7px 10px; font-weight: 800; font-size: 15px;
                           color: {{ $completionRate >= 80 ? '#16a34a' : ($completionRate >= 50 ? '#d97706' : '#b91c1c') }};">
                    {{ $completionRate }}%
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Hours progress bar --}}
    <div style="margin: 10px 0 16px; background: #f1f5f9; border-radius: 6px; height: 10px; overflow: hidden;">
        <div style="height: 100%; border-radius: 6px; width: {{ $completionRate }}%;
                    background: {{ $completionRate >= 80 ? '#16a34a' : ($completionRate >= 50 ? '#d97706' : '#b91c1c') }};
                    -webkit-print-color-adjust: exact; print-color-adjust: exact;"></div>
    </div>

    {{-- Alerts --}}
    @if($shortHourCount > 0)
    <div style="border: 1px solid #fde68a; background: #fffbeb; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; font-size: 12px; color: #92400e;">
        ⚠ {{ $shortHourCount }} {{ __('general.teaching_session') }} {{ __('general.actual_hours') }} أقل من المخطط
    </div>
    @endif

    {{-- Sessions Detail Table --}}
    <table>
        <thead>
            <tr>
                <th style="width: 32px">#</th>
                <th style="width: 130px">{{ __('general.date') }}</th>
                <th style="width: 80px">{{ __('general.period') }}</th>
                <th>{{ __('general.actual_teacher') }}</th>
                <th style="width: 100px">{{ __('general.status') }}</th>
                <th style="width: 95px">{{ __('general.actual_hours') }}</th>
                <th>{{ __('general.notes') }} / {{ __('general.cancellation_reason') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions->sortBy('date') as $index => $session)
                @php
                    $isSubstituted = $session->status === 'substituted' ||
                        ($session->actual_teacher_id && $session->primary_teacher_id
                            && (int) $session->actual_teacher_id !== (int) $session->primary_teacher_id);
                    $isShortHour   = (float)$session->actual_hours > 0 && (float)$session->actual_hours < (float)$session->planned_hours;

                    $rowBg = match(true) {
                        $session->status === 'cancelled'  => 'background: #fff1f2;',
                        $session->status === 'postponed'  => 'background: #fffbeb;',
                        $isSubstituted                    => 'background: #f0f9ff;',
                        $isShortHour                      => 'background: #fefce8;',
                        default                           => '',
                    };

                    $statusBadge = match($session->status) {
                        'completed'   => 'background:#dcfce7;color:#15803d;',
                        'substituted' => 'background:#e0f2fe;color:#0369a1;',
                        'cancelled'   => 'background:#fee2e2;color:#b91c1c;',
                        'postponed'   => 'background:#fef3c7;color:#92400e;',
                        default       => 'background:#f1f5f9;color:#475569;',
                    };
                @endphp
                <tr style="{{ $rowBg }}">
                    <td style="text-align: center; color: #64748b;">{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($session->date)->translatedFormat('l d/m/Y') }}</td>
                    <td style="font-size: 11px; color: #475569;">{{ $session->period?->name ?? '—' }}</td>
                    <td>
                        {{ $session->actualTeacher?->name ?? '—' }}
                        @if($isSubstituted)
                        <span style="display:inline-block; font-size:10px; font-weight:700;
                                     background:#e0f2fe; color:#0369a1; border-radius:999px;
                                     padding:1px 7px; margin-inline-start:4px;">
                            {{ __('general.status_substituted') }}
                        </span>
                        @endif
                    </td>
                    <td>
                        <span class="badge" style="font-size:11px; border-radius:999px; padding:2px 8px; font-weight:700; {{ $statusBadge }}">
                            {{ __("general.status_{$session->status}") }}
                        </span>
                    </td>
                    <td style="{{ $isShortHour ? 'color:#b45309; font-weight:700;' : '' }}">
                        {{ number_format((float) $session->actual_hours, 1) }}
                        @if($isShortHour)
                        / {{ number_format((float) $session->planned_hours, 1) }}
                        @endif
                        {{ __('general.hours_short') }}
                        @if($isShortHour)
                        <span style="font-size:9px; color:#b45309;">↓</span>
                        @endif
                    </td>
                    <td>
                        @if($session->status === 'cancelled' && $session->cancellation_reason)
                            <span style="color:#b91c1c; font-weight:700;">{{ $session->cancellation_reason }}</span>
                        @elseif($session->status === 'postponed' && $session->cancellation_reason)
                            <span style="color:#b45309;">{{ $session->cancellation_reason }}</span>
                        @else
                            {{ $session->notes ?? '—' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 24px;">
                        {{ __('general.no_sessions_recorded') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f8fafc; font-weight: 700;">
                <td colspan="5" style="text-align: end; padding: 8px 10px;">{{ __('general.total') }}</td>
                <td style="font-weight: 800; color: #4f46e5;">
                    {{ number_format($totalActualHours, 1) }} {{ __('general.hours_short') }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Signatures --}}
    <div style="margin-top: 48px; display: flex; justify-content: space-between; padding: 0 24px; page-break-inside: avoid;">
        <div style="text-align: center; min-width: 180px;">
            <p style="font-weight: 700; margin-bottom: 40px; font-size: 13px;">{{ __('general.teacher_signature') }}</p>
            <p style="border-bottom: 1px solid #94a3b8; margin: 0 16px;"></p>
            <p style="margin-top: 6px; font-size: 11px; color: #64748b;">{{ $batch->teacher?->name ?? '—' }}</p>
        </div>
        <div style="text-align: center; min-width: 180px;">
            <p style="font-weight: 700; margin-bottom: 40px; font-size: 13px;">{{ __('general.academic_supervisor_signature') }}</p>
            <p style="border-bottom: 1px solid #94a3b8; margin: 0 16px;"></p>
            <p style="margin-top: 6px; font-size: 11px; color: #64748b; opacity: 0;">—</p>
        </div>
    </div>
@endsection
