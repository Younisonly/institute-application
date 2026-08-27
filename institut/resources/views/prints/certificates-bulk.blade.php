@extends('prints.layout')

@section('title', __('general.certificates'))

@section('content')
    <style>
        .cert-frame {
            border: 3px double #00B7EB;
            border-radius: 16px;
            padding: 22px 26px;
            text-align: center;
            background: #fff;
            page-break-after: always;
        }
        .cert-frame:last-child { page-break-after: auto; }
        .cert-frame::before {
            content: '';
            position: absolute;
            inset: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            pointer-events: none;
        }
        .cert-title-ar { font-size: 24px; font-weight: 800; color: #0f172a; }
        .cert-title-en { font-size: 12px; font-weight: 700; color: #00B7EB; text-transform: uppercase; letter-spacing: 2px; margin: 4px 0 14px; }
        .cert-student-label { font-size: 12px; color: #64748b; margin-top: 12px; }
        .cert-student-name {
            display: inline-block;
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2px solid #00B7EB;
            padding: 2px 24px 5px;
            margin: 4px 0 12px;
        }
        .cert-text { font-size: 14px; color: #334155; line-height: 2; max-width: 600px; margin: 0 auto; }
        .cert-marks { display: flex; justify-content: center; gap: 14px; margin: 14px auto 4px; flex-wrap: wrap; }
        .cert-mark-box { border: 1px solid #e2e8f0; border-radius: 10px; padding: 6px 16px; background: #f8fafc; }
        .cert-mark-box .label { font-size: 11px; color: #64748b; }
        .cert-mark-box .value { font-size: 15px; font-weight: 800; color: #0f172a; }
        .cert-mark-box .value.grade { color: #00B7EB; }
        .cert-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #475569;
            border-top: 1px dashed #cbd5e1;
            margin-top: 16px;
            padding-top: 8px;
        }
    </style>

    @foreach ($registrations as $registration)
        <div class="cert-frame">
            <div class="cert-title-ar">{{ __('general.certificate_of_completion') }}</div>
            <div class="cert-title-en">Certificate of Completion</div>

            <div class="cert-student-label">{{ __('general.certificate_issued_to') }}</div>
            <div class="cert-student-name">{{ $registration->student->name }}</div>

            <p class="cert-text">
                {{ __('general.certificate_student', [
                    'student' => $registration->student->name,
                    'course' => $registration->course->name,
                    'batch' => $registration->batch?->name ?? '—',
                    'total' => number_format((float) $registration->grade_total),
                    'full' => $registration->grades['full_mark'] ?? $registration->course->full_mark ?? '—',
                    'grade' => $registration->grades['grade'] ?? '—',
                ]) }}
            </p>

            <div class="cert-marks">
                <div class="cert-mark-box">
                    <div class="label">{{ __('general.mark') }}</div>
                    <div class="value">{{ number_format((float) $registration->grade_total) }} / {{ $registration->grades['full_mark'] ?? $registration->course->full_mark ?? '—' }}</div>
                </div>
                <div class="cert-mark-box">
                    <div class="label">{{ __('general.result') }}</div>
                    <div class="value grade">{{ $registration->grades['grade'] ?? __('general.passed') }}</div>
                </div>
            </div>

            <div class="cert-meta">
                <span>{{ __('general.certificate_no') }}: {{ $registration->start_month }}-{{ str_pad((string) $registration->id, 4, '0', STR_PAD_LEFT) }}</span>
                <span>{{ now()->translatedFormat('d F Y') }}</span>
            </div>

            <div class="signature-row">
                <div class="signature">
                    <div class="signature-label">{{ $registration->course->teacher?->name ?? __('general.teacher') }}</div>
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ __('general.teacher') }}</div>
                </div>
                <div class="signature">
                    <div class="stamp-box"></div>
                </div>
                <div class="signature">
                    <div class="signature-label">{{ $settings->localized_name }}</div>
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ __('general.stamp') }}</div>
                </div>
            </div>
        </div>
    @endforeach

    @if ($registrations->isEmpty())
        <p class="empty">{{ __('general.certificate_not_ready') }}</p>
    @endif
@endsection