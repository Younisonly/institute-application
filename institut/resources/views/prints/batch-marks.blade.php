@extends('prints.layout')

@section('title', __('general.marks_sheet'))

@section('content')
    @php
        $schema = $batch->course?->grading_schema ?? [];
    @endphp

    <h2 class="title">{{ __('general.marks_sheet') }}</h2>

    <div class="info-grid">
        <div class="item"><span class="label">{{ __('general.course') }}</span><span class="value">{{ $batch->course->name }}</span></div>
        <div class="item"><span class="label">{{ __('general.batch') }}</span><span class="value">{{ $batch->name }}</span></div>
        <div class="item"><span class="label">{{ __('general.period') }}</span><span class="value">{{ $batch->periods_label ?? '—' }}</span></div>
        <div class="item"><span class="label">{{ __('general.teacher') }}</span><span class="value">{{ $batch->course->teacher?->name ?? __('general.teacher') }}</span></div>
        <div class="item"><span class="label">{{ __('general.students_count') }}</span><span class="value">{{ $registrations->count() }}</span></div>
        <div class="item"><span class="label">{{ __('general.graded_count') }}</span><span class="value">{{ $registrations->filter(fn (mixed $r): bool => $r->graded_at !== null)->count() }}</span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th>{{ __('general.student') }}</th>
                <th style="width:80px">{{ __('general.phone') }}</th>
                @if ($schema !== [])
                    @foreach ($schema as $item)
                        <th>{{ $item['label'] ?? '' }}</th>
                    @endforeach
                @endif
                <th style="width:90px">{{ __('general.mark') }}</th>
                <th style="width:110px">{{ __('general.result') }}</th>
                <th style="width:100px">{{ __('general.grade') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registrations as $index => $registration)
                @php($grades = $registration->grades ?? [])
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $registration->student->name }}</td>
                    <td>{{ $registration->student->phone ?? '—' }}</td>
                    @if ($schema !== [])
                        @foreach ($schema as $item)
                            <td>
                                @if (isset($grades[$item['label'] ?? '']) && is_numeric($grades[$item['label'] ?? '']))
                                    {{ number_format((float) $grades[$item['label'] ?? '']) }}
                                @else
                                    —
                                @endif
                            </td>
                        @endforeach
                    @endif
                    <td>
                        {{ $grades !== [] && $registration->grade_total !== null ? number_format((float) $registration->grade_total) : '—' }}
                        / {{ $grades['full_mark'] ?? $batch->course->full_mark ?? '—' }}
                    </td>
                    <td>
                        @if ($grades['passed'] ?? null)
                            <span class="badge success">{{ __('general.passed') }}</span>
                        @elseif (($grades['passed'] ?? null) === false)
                            <span class="badge danger">{{ __('general.failed') }}</span>
                        @else
                            <span class="badge gray">{{ __('general.not_graded') }}</span>
                        @endif
                    </td>
                    <td>{{ $grades['grade'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 6 + count($schema) }}" class="empty">{{ __('general.no_students') }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection