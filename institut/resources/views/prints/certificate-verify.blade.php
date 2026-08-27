@extends('prints.layout')

@section('title', __('general.verify_certificate'))

@section('content')
    <style>
        .verify-frame {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px 28px;
            text-align: center;
            background: #fff;
            max-width: 640px;
            margin: 0 auto;
        }
        .verify-search { margin-bottom: 16px; }
        .verify-search input {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            width: 220px;
            text-align: center;
            text-transform: uppercase;
        }
        .verify-search button {
            border: none;
            border-radius: 8px;
            background: #00B7EB;
            color: #fff;
            font-weight: 700;
            padding: 8px 16px;
            font-size: 13px;
            cursor: pointer;
        }
        .verify-badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 14px;
            margin-bottom: 14px;
        }
        .verify-badge.valid { background: #dcfce7; color: #15803d; }
        .verify-badge.invalid { background: #fee2e2; color: #b91c1c; }
        .verify-badge.missing { background: #f1f5f9; color: #64748b; }
        .verify-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #e2e8f0;
            padding: 8px 0;
            font-size: 13px;
        }
        .verify-row .label { color: #64748b; }
        .verify-row .value { font-weight: 700; color: #0f172a; }
    </style>

    <div class="verify-frame">
        <form class="verify-search" method="get" action="{{ route('certificates.verify') }}">
            <input
                type="text"
                name="code"
                value="{{ $code }}"
                placeholder="{{ __('general.verify_code_placeholder') }}"
                autofocus
            >
            <button type="submit">{{ __('general.verify') }}</button>
        </form>

        @if ($code === '')
            <p>{{ __('general.verify_search_hint') }}</p>
        @elseif ($certificate === null)
            <div class="verify-badge missing">{{ __('general.certificate_not_found') }}</div>
            <p>{{ __('general.verify_code_unknown', ['code' => $code]) }}</p>
        @else
            @if ($certificate->isVoided())
                <div class="verify-badge invalid">{{ __('general.certificate_voided') }}</div>
            @else
                <div class="verify-badge valid">{{ __('general.certificate_valid') }}</div>
            @endif

            <div class="verify-row">
                <span class="label">{{ __('general.certificate_no') }}</span>
                <span class="value">{{ $certificate->certificate_no }}</span>
            </div>
            <div class="verify-row">
                <span class="label">{{ __('general.student') }}</span>
                <span class="value">{{ $certificate->student->name }}</span>
            </div>
            <div class="verify-row">
                <span class="label">{{ __('general.program') }}</span>
                <span class="value">{{ $certificate->program->name }}</span>
            </div>
            <div class="verify-row">
                <span class="label">{{ __('general.issue_date') }}</span>
                <span class="value">{{ $certificate->issue_date->format('d/m/Y') }}</span>
            </div>
            <div class="verify-row">
                <span class="label">{{ __('general.completion_date') }}</span>
                <span class="value">{{ $certificate->completion_date->format('d/m/Y') }}</span>
            </div>
            @if ($certificate->isVoided())
                <div class="verify-row">
                    <span class="label">{{ __('general.reason') }}</span>
                    <span class="value">{{ $certificate->void_reason }}</span>
                </div>
            @endif

            <p>{{ __('general.verify_issued_by', ['institute' => $settings->localized_name]) }}</p>
        @endif
    </div>
@endsection