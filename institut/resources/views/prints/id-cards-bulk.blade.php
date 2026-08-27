@extends('prints.layout')

@section('title', __('general.print_course_cards').' — '.$course->name)

@section('content')
    <h2 class="title">{{ __('general.print_course_cards') }} — {{ $course->name }}</h2>
    <div style="text-align:center;color:#64748b;margin-bottom:12px">{{ $registrations->count() }} {{ __('general.student') }}</div>

    <style>
        .id-card-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .id-card { width: auto; min-height: 46mm; border-radius: 4mm; padding: 8px; gap: 4px; }
        .id-card-photo-wrap { width: 60px; height: 74px; }
        .id-card-photo-placeholder { font-size: 22px; }
        .id-card-name { font-size: 10px; }
        .id-card-sub { font-size: 7px; }
        .id-card-label { min-width: 44px; font-size: 7px; }
        .id-card-value { font-size: 8.5px; }
        .id-card-footer { font-size: 7px; padding-top: 2px; }
        @media print {
            .id-card-grid { page-break-after: always; }
            .id-card { page-break-inside: avoid; }
        }
    </style>

    @foreach ($registrations as $chunkIndex => $chunk)
        <div class="id-card-grid">
            @foreach ($chunk as $registration)
                <div class="id-card">
                    <div class="id-card-header">
                        @if ($settings->logo_path)
                            <img src="{{ asset(\Illuminate\Support\Facades\Storage::url($settings->logo_path)) }}" class="id-card-logo" alt="">
                        @endif
                        <div class="id-card-institute">
                            <div class="id-card-name">{{ $settings->localized_name }}</div>
                            <div class="id-card-sub">{{ __('general.id_card') }}</div>
                        </div>
                    </div>
                    <div class="id-card-body">
                        <div class="id-card-photo-wrap">
                            @if ($registration->student->photo_path)
                                <img src="{{ asset(\Illuminate\Support\Facades\Storage::url($registration->student->photo_path)) }}" class="id-card-photo" alt="">
                            @else
                                <div class="id-card-photo-placeholder">{{ mb_substr($registration->student->name, 0, 1) }}</div>
                            @endif
                        </div>
                        <div class="id-card-info">
                            <div class="id-card-row">
                                <span class="id-card-label">{{ __('general.name') }}</span>
                                <span class="id-card-value">{{ $registration->student->name }}</span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">{{ __('general.gender') }}</span>
                                <span class="id-card-value">{{ $registration->student->gender ? __("general.{$registration->student->gender}") : '—' }}</span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">{{ __('general.phone') }}</span>
                                <span class="id-card-value">{{ $registration->student->phone ?? '—' }}</span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">{{ __('general.course') }}</span>
                                <span class="id-card-value">{{ $registration->course?->name }}</span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">{{ __('general.period') }}</span>
                                <span class="id-card-value">{{ $registration->batch?->periods_label ?? '—' }}</span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">{{ __('general.start_month') }}</span>
                                <span class="id-card-value">{{ $registration->start_month }}</span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">{{ __('general.card_student_id') }}</span>
                                <span class="id-card-value">{{ $registration->student->student_code ?? '#' . str_pad((string) $registration->student_id, 5, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="id-card-footer">{{ $settings->phone }}</div>
                </div>
            @endforeach
        </div>
    @endforeach
@endsection
