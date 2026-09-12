<x-filament-panels::page>
    @php
        $batch    = $this->record;
        $sessions = $batch->teachingSessions()
            ->with(['primaryTeacher', 'actualTeacher', 'createdBy', 'period'])
            ->orderBy('date', 'asc')
            ->get();

        $totalSessions    = $sessions->count();
        $completedCount   = $sessions->where('status', 'completed')->count();
        $substitutedCount = $sessions->where('status', 'substituted')->count();
        $cancelledCount   = $sessions->where('status', 'cancelled')->count();
        $postponedCount   = $sessions->where('status', 'postponed')->count();

        $totalActualHours  = (float) $sessions->sum('actual_hours');
        $totalPlannedHours = (float) $sessions->sum('planned_hours');

        $completionPct = $totalPlannedHours > 0
            ? min(100, round(($totalActualHours / $totalPlannedHours) * 100, 1))
            : ($totalSessions > 0
                ? min(100, round((($completedCount + $substitutedCount) / $totalSessions) * 100, 1))
                : 0);

        $substitutionPct = ($completedCount + $substitutedCount) > 0
            ? round(($substitutedCount / ($completedCount + $substitutedCount)) * 100, 1)
            : 0;
    @endphp

    {{-- ── Batch meta summary bar ────────────────── --}}
    <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <span class="font-semibold text-gray-900 dark:text-white">{{ $batch->course?->name ?? '—' }}</span>
            <span class="text-gray-400 dark:text-gray-500">|</span>
            <span class="text-gray-500 dark:text-gray-400">
                {{ __('general.primary_teacher') }}:
                <span class="font-semibold text-gray-900 dark:text-white">{{ $batch->teacher?->name ?? '—' }}</span>
            </span>
            @if($batch->start_date || $batch->end_date)
            <span class="text-gray-400 dark:text-gray-500">|</span>
            <span class="text-gray-500 dark:text-gray-400">
                {{ $batch->start_date?->format('d/m/Y') ?? '—' }}
                →
                {{ $batch->end_date?->format('d/m/Y') ?? '—' }}
            </span>
            @endif
            @if($batch->periods?->isNotEmpty())
            <span class="text-gray-400 dark:text-gray-500">|</span>
            <span class="text-gray-500 dark:text-gray-400">
                <x-filament::badge>{{ $batch->periods->pluck('name')->join('، ') }}</x-filament::badge>
            </span>
            @endif
        </div>
    </div>

    {{-- ── Stat cards ─────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">

        {{-- Total --}}
        <div class="fi-section rounded-xl bg-white p-5 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.number_of_sessions') }}</div>
            <div class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ $totalSessions }}</div>
            <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ __('general.teaching_session') }}</div>
        </div>

        {{-- Completed --}}
        <div class="fi-section rounded-xl bg-white p-5 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.status_completed') }}</div>
            <div class="mt-1 text-3xl font-bold text-success-600 dark:text-success-400">{{ $completedCount }}</div>
            <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                {{ $totalSessions > 0 ? round(($completedCount / $totalSessions) * 100) : 0 }}%
                {{ __('general.total') }}
            </div>
        </div>

        {{-- Substituted --}}
        <div class="fi-section rounded-xl bg-white p-5 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.status_substituted') }}</div>
            <div class="mt-1 text-3xl font-bold text-info-600 dark:text-info-400">{{ $substitutedCount }}</div>
            <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                @if($substitutionPct > 0)
                    {{ $substitutionPct }}% {{ __('general.substitution_ratio') }}
                @else
                    —
                @endif
            </div>
        </div>

        {{-- Cancelled + Postponed --}}
        <div class="fi-section rounded-xl bg-white p-5 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('general.status_cancelled') }} / {{ __('general.status_postponed') }}
            </div>
            <div class="mt-1 text-3xl font-bold {{ ($cancelledCount + $postponedCount) > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">
                {{ $cancelledCount + $postponedCount }}
            </div>
            <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                @if($cancelledCount > 0 || $postponedCount > 0)
                    {{ $cancelledCount }} {{ __('general.status_cancelled') }}
                    @if($postponedCount > 0) · {{ $postponedCount }} {{ __('general.status_postponed') }} @endif
                @else
                    —
                @endif
            </div>
        </div>

        {{-- Completion rate + hours --}}
        <div class="fi-section rounded-xl bg-white p-5 shadow-sm dark:bg-gray-900 col-span-2 sm:col-span-1">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.completion_rate') }}</div>
            <div class="mt-1 text-3xl font-bold
                {{ $completionPct >= 80 ? 'text-success-600 dark:text-success-400'
                    : ($completionPct >= 50 ? 'text-warning-600 dark:text-warning-400'
                    : ($totalSessions > 0 ? 'text-danger-600 dark:text-danger-400'
                    : 'text-gray-900 dark:text-white')) }}">
                {{ $totalSessions > 0 ? $completionPct . '%' : '—' }}
            </div>
            @if($totalPlannedHours > 0)
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-full rounded-full transition-all duration-500
                    {{ $completionPct >= 80 ? 'bg-success-500'
                        : ($completionPct >= 50 ? 'bg-warning-500' : 'bg-danger-500') }}"
                     style="width: {{ $completionPct }}%"></div>
            </div>
            <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                {{ number_format($totalActualHours, 1) }} / {{ number_format($totalPlannedHours, 1) }} {{ __('general.hours_short') }}
            </div>
            @else
            <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">—</div>
            @endif
        </div>

    </div>

    {{-- ── Native table ────────────────────────────── --}}
    <x-filament::section :heading="__('general.sessions_detail')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
