<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6 print:shadow-none print:ring-0 print:bg-transparent print:p-0">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <img 
                    src="{{ $record->photo_path ? Storage::url($record->photo_path) : 'https://ui-avatars.com/api/?name='.urlencode($record->name) }}" 
                    alt="{{ $record->name }}" 
                    class="h-16 w-16 rounded-full object-cover ring-2 ring-primary-500 print:ring-0 print:h-12 print:w-12"
                />
                <div>
                    <h2 class="text-xl font-bold text-gray-950 dark:text-white print:text-black">
                        {{ $record->name }}
                    </h2>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-sm text-gray-500 dark:text-gray-400 print:text-gray-700">
                            {{ $record->jobTitle?->name ?? '—' }}
                        </span>
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $record->is_teacher ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400' : 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-700/10 dark:bg-gray-400/10 dark:text-gray-400' }} print:bg-transparent print:ring-0 print:px-0">
                            {{ $record->is_teacher ? __('general.teacher') : __('general.employee') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full print:gap-2 mt-6 md:mt-0">
                <div class="rounded-xl bg-gray-50/50 dark:bg-white/5 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 print:bg-transparent print:ring-gray-300">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400 print:text-gray-600">{{ __('general.total_present_days') }}</span>
                    <p class="text-2xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400 mt-1 print:text-black">{{ $this->stats['present'] }}</p>
                </div>
                <div class="rounded-xl bg-gray-50/50 dark:bg-white/5 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 print:bg-transparent print:ring-gray-300">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400 print:text-gray-600">{{ __('general.total_absent_days') }}</span>
                    <p class="text-2xl font-semibold tracking-tight text-rose-600 dark:text-rose-400 mt-1 print:text-black">{{ $this->stats['absent'] }}</p>
                </div>
                <div class="rounded-xl bg-gray-50/50 dark:bg-white/5 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 print:bg-transparent print:ring-gray-300">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400 print:text-gray-600">{{ __('general.total_hours_worked') }}</span>
                    <p class="text-2xl font-semibold tracking-tight text-sky-600 dark:text-sky-400 mt-1 print:text-black">{{ number_format($this->stats['hours'], 2) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50/50 dark:bg-white/5 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 print:bg-transparent print:ring-gray-300">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400 print:text-gray-600">{{ __('general.attendance_rate') }}</span>
                    <p class="text-2xl font-semibold tracking-tight text-indigo-600 dark:text-indigo-400 mt-1 print:text-black">{{ $this->stats['rate'] }}%</p>
                </div>
            </div>
        </div>
    </div>

    <div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
