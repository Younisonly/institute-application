<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="applyFilters">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    @php($registration = $this->getSelectedRegistration())
    @php($courseRegistrations = $this->getCourseRegistrations())
    @php($settings = $this->getSettings())

    @if ($courseRegistrations->isNotEmpty())
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold">{{ __('general.print_course_cards') }} ({{ $courseRegistrations->count() }})</h3>
                <span class="text-sm text-gray-500">{{ __('general.select_course_for_bulk') }}</span>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($courseRegistrations as $courseRegistration)
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
                                @if ($courseRegistration->student->photo_path)
                                    <img src="{{ asset(\Illuminate\Support\Facades\Storage::url($courseRegistration->student->photo_path)) }}" class="id-card-photo" alt="">
                                @else
                                    <div class="id-card-photo-placeholder">{{ mb_substr($courseRegistration->student->name, 0, 1) }}</div>
                                @endif
                            </div>
                            <div class="id-card-info">
                                <div class="id-card-row">
                                    <span class="id-card-label">{{ __('general.name') }}</span>
                                    <span class="id-card-value">{{ $courseRegistration->student->name }}</span>
                                </div>
                                <div class="id-card-row">
                                    <span class="id-card-label">{{ __('general.course') }}</span>
                                    <span class="id-card-value">{{ $courseRegistration->course?->name }}</span>
                                </div>
                                <div class="id-card-row">
                                    <span class="id-card-label">{{ __('general.period') }}</span>
                                    <span class="id-card-value">{{ $courseRegistration->batch?->periods_label ?? '—' }}</span>
                                </div>
                                <div class="id-card-row">
                                    <span class="id-card-label">{{ __('general.start_month') }}</span>
                                    <span class="id-card-value">{{ $courseRegistration->start_month }}</span>
                                </div>
                                <div class="id-card-row">
                                    <span class="id-card-label">{{ __('general.card_student_id') }}</span>
                                    <span class="id-card-value">#{{ str_pad((string) $courseRegistration->student_id, 5, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="id-card-footer">{{ $settings->phone }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($registration)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900">
            <h3 class="mb-4 text-sm font-semibold">{{ __('general.id_card') }}</h3>
            <div class="flex justify-center">
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
                                <span class="id-card-value">#{{ str_pad((string) $registration->student_id, 5, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="id-card-footer">{{ $settings->phone }}</div>
                </div>
            </div>
        </div>
    @else
        <x-filament::section>
            <p class="py-8 text-center text-sm text-gray-500">{{ __('general.select_student') }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
