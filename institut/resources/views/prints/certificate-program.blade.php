@extends('prints.layout')

@section('title', __('general.certificate_of_completion'))

@section('content')
    <style>
        .cert-frame {
            border: 3px double #00B7EB;
            border-radius: 16px;
            padding: 24px 28px;
            text-align: center;
            position: relative;
            background: #fff;
        }
        .cert-frame::before {
            content: '';
            position: absolute;
            inset: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            pointer-events: none;
        }
        .cert-title-ar { font-size: 26px; font-weight: 800; color: #0f172a; }
        .cert-title-en { font-size: 13px; font-weight: 700; color: #00B7EB; text-transform: uppercase; letter-spacing: 2px; margin: 4px 0 18px; }
        .cert-student-label { font-size: 13px; color: #64748b; margin-top: 14px; }
        .cert-student-name {
            display: inline-block;
            font-size: 30px;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2px solid #00B7EB;
            padding: 2px 28px 6px;
            margin: 6px 0 14px;
        }
        .cert-text { font-size: 15px; color: #334155; line-height: 2; max-width: 620px; margin: 0 auto; }
        .cert-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px auto 6px;
            font-size: 12px;
        }
        .cert-table th, .cert-table td {
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            color: #334155;
        }
        .cert-table th { background: #f8fafc; color: #64748b; font-weight: 700; }
        .cert-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #475569;
            border-top: 1px dashed #cbd5e1;
            margin-top: 20px;
            padding-top: 10px;
        }
        .verify-note {
            display: inline-block;
            margin-top: 12px;
            padding: 5px 14px;
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            font-size: 11px;
            color: #475569;
        }
    </style>

    <div class="cert-frame">
        <div class="cert-title-ar">{{ __('general.certificate_of_completion') }}</div>
        <div class="cert-title-en">Certificate of Completion</div>

        <div class="cert-student-label">{{ __('general.certificate_issued_to') }}</div>
        <div class="cert-student-name">{{ $certificate->student->name }}</div>

        <p class="cert-text">
            {{ __('general.certificate_program_student', [
                'student' => $certificate->student->name,
                'program' => $certificate->program->name,
                'issue' => $certificate->issue_date->format('d/m/Y'),
            ]) }}
        </p>

        @if ($certificate->earned_courses)
            <table class="cert-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('general.course') }}</th>
                        <th>{{ __('general.batch') }}</th>
                        <th>{{ __('general.year') }}</th>
                        <th>{{ __('general.mark') }}</th>
                        <th>{{ __('general.result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($certificate->earned_courses as $index => $course)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $course['course'] }}</td>
                            <td>{{ $course['batch'] }}</td>
                            <td>{{ $course['year'] }}</td>
                            <td>{{ $course['mark'] ?? '—' }}</td>
                            <td>{{ $course['result'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="cert-meta">
            <span>{{ __('general.cert_no_short') }}: {{ $certificate->certificate_no }}</span>
            <span>{{ __('general.issue_date') }}: {{ $certificate->issue_date->format('d/m/Y') }}</span>
        </div>

        <div class="verify-note">
            {{ __('general.verify_with_code', ['code' => $certificate->verification_code]) }}
        </div>

        <div class="signature-row">
            <div class="signature">
                <div class="signature-label">{{ $certificate->issuedBy?->name ?? __('general.stamp') }}</div>
                <div class="signature-line"></div>
                <div class="signature-label">{{ __('general.issued_by') }}</div>
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
@endsection