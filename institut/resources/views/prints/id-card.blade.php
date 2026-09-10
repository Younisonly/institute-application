@extends('prints.layout')

@section('title', __('general.id_card').' — '.$registration->student->name)

@section('content')
    <div class="card-wrap">
        <div class="id-card" style="box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border-color: #00B7EB;">
            <div class="id-card-header" style="background: linear-gradient(135deg, #f0faff, #e0f2fe); margin: -12px -12px 10px -12px; padding: 8px 12px; border-bottom: 2px solid #00B7EB;">
                @if ($settings->logo_path)
                    <img src="{{ asset(\Illuminate\Support\Facades\Storage::url($settings->logo_path)) }}" class="id-card-logo" alt="">
                @endif
                <div class="id-card-institute">
                    <div class="id-card-name" style="color: #0369a1;">{{ $settings->localized_name }}</div>
                    <div class="id-card-sub" style="color: #00B7EB; letter-spacing: 0.5px;">{{ __('general.id_card') }}</div>
                </div>
                <div style="font-size: 8px; color: #64748b; font-weight: 700;">{{ $registration->start_month }}</div>
            </div>
            <div class="id-card-body">
                <div class="id-card-photo-wrap" style="border: 2px solid #00B7EB; border-radius: 6px;">
                    @if ($registration->student->photo_path)
                        <img src="{{ asset(\Illuminate\Support\Facades\Storage::url($registration->student->photo_path)) }}" class="id-card-photo" alt="">
                    @else
                        <div class="id-card-photo-placeholder" style="background: linear-gradient(135deg, #00B7EB, #0284c7); color: #fff;">{{ mb_substr($registration->student->name, 0, 1) }}</div>
                    @endif
                </div>
                <div class="id-card-info">
                    <div class="id-card-row">
                        <span class="id-card-label">{{ __('general.name') }}</span>
                        <span class="id-card-value" style="font-size: 11px; color: #0f172a;">{{ $registration->student->name }}</span>
                    </div>
                    <div class="id-card-row">
                        <span class="id-card-label">{{ __('general.card_student_id') }}</span>
                        <span class="id-card-value" style="color: #0369a1;">{{ $registration->student->student_code ?? '#' . str_pad((string) $registration->student_id, 5, '0', STR_PAD_LEFT) }}</span>
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
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; border-inline-start: 1px dashed #cbd5e1; padding-inline-start: 8px;">
                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(42)->generate(route('id-cards.print', $registration)) !!}
                    <span style="font-size: 7px; color: #64748b;">{{ __('general.verify') }}</span>
                </div>
            </div>
            <div class="id-card-footer" style="background: #f8fafc; margin: 4px -12px -12px -12px; padding: 6px 12px;">
                {{ $settings->phone }} • {{ $settings->address }}
            </div>
        </div>
    </div>
@endsection
