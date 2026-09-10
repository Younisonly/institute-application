<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <img 
                    src="{{ $record->photo_path ? Storage::url($record->photo_path) : 'https://ui-avatars.com/api/?name='.urlencode($record->name) }}" 
                    alt="{{ $record->name }}" 
                    class="h-16 w-16 rounded-full object-cover ring-2 ring-primary-500"
                />
                <div>
                    <h2 class="text-xl font-bold text-gray-950 dark:text-white">
                        {{ $record->name }}
                    </h2>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $record->jobTitle?->name ?? '—' }}
                        </span>
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $record->is_teacher ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400' : 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-700/10 dark:bg-gray-400/10 dark:text-gray-400' }}">
                            {{ $record->is_teacher ? __('general.teacher') : __('general.employee') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full md:w-auto">
                <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-800/50">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('general.total_present_days') }}</span>
                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $this->stats['present'] }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-800/50">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('general.total_absent_days') }}</span>
                    <p class="text-lg font-bold text-rose-600 dark:text-rose-400">{{ $this->stats['absent'] }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-800/50">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('general.total_hours_worked') }}</span>
                    <p class="text-lg font-bold text-sky-600 dark:text-sky-400">{{ number_format($this->stats['hours'], 2) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-800/50">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('general.attendance_rate') }}</span>
                    <p class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ $this->stats['rate'] }}%</p>
                </div>
            </div>
        </div>
    </div>

    <div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
