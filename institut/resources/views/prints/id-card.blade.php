@extends('prints.layout')

@section('title', __('general.id_card').' — '.$registration->student->name)

@section('content')
    <div class="card-wrap">
        <div class="id-card">
            <div class="id-card-header">
                @if ($settings->logo_path)
                    <img src="{{ asset(\Illuminate\Support\Facades\Storage::url($settings->logo_path)) }}" class="id-card-logo" alt="">
                @endif
                <div class="id-card-institute">
                    <div class="id-card-name">{{ $settings->localized_name }}</div>
                    <div class="id-card-sub">{{ __('general.id_card') }}</div>
                </div>
                <div style="font-size:8px;color:#94a3b8">{{ $registration->start_month }}</div>
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
                        <span class="id-card-value">{{ $registration->course->name }}</span>
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
                        <span class="id-card-label">{{ __('general.end_month') }}</span>
                        <span class="id-card-value">{{ $registration->expected_end }}</span>
                    </div>
                    <div class="id-card-row">
                        <span class="id-card-label">{{ __('general.card_student_id') }}</span>
                        <span class="id-card-value">{{ $registration->student->student_code ?? '#' . str_pad((string) $registration->student_id, 5, '0', STR_PAD_LEFT) }}</span>
                    </div>
                </div>
            </div>
            <div class="id-card-footer">{{ $settings->phone }} • {{ $settings->address }}</div>
            <div class="id-card-stamp">
                <span class="stamp-box stamp-small"></span>
            </div>
        </div>
    </div>
@endsection
