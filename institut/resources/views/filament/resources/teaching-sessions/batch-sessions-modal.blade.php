<div class="space-y-6">
    @php
        $batch = $getRecord();
        $sessions = $batch->teachingSessions()
            ->with(['primaryTeacher', 'actualTeacher', 'createdBy'])
            ->orderBy('date', 'desc')
            ->get();
        $totalDays = $sessions->count();
        $completedCount = $sessions->where('status', 'completed')->count();
        $substitutedCount = $sessions->where('status', 'substituted')->count();
        $cancelledCount = $sessions->where('status', 'cancelled')->count();
        $postponedCount = $sessions->where('status', 'postponed')->count();
        $totalActualHours = $sessions->sum('actual_hours');
        $totalPlannedHours = $sessions->sum('planned_hours');
    @endphp

    <!-- Header & Print Action -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 ring-1 ring-gray-950/5 dark:ring-white/10">
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                {{ $batch->name }} ({{ $batch->course?->name ?? '—' }})
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('general.primary_teacher') }}: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $batch->teacher?->name ?? '—' }}</span>
                @if($batch->start_date)
                    | {{ __('general.start_date') }}: {{ $batch->start_date->format('d/m/Y') }}
                @endif
                @if($batch->end_date)
                    - {{ __('general.end_date') }}: {{ $batch->end_date->format('d/m/Y') }}
                @endif
            </p>
        </div>
        <a 
            href="{{ route('reports.teaching-sessions.batch.print', ['batch' => $batch]) }}" 
            target="_blank"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow-sm transition-colors"
        >
            <x-heroicon-o-printer class="w-4 h-4" />
            <span>{{ __('general.print_report') }}</span>
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3">
        <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/40 text-center ring-1 ring-gray-950/5 dark:ring-white/5">
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('general.number_of_sessions') }}</span>
            <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $totalDays }}</p>
        </div>
        <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 text-center ring-1 ring-emerald-500/10">
            <span class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('general.status_completed') }}</span>
            <p class="text-xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">{{ $completedCount }}</p>
        </div>
        <div class="p-3 rounded-lg bg-sky-50 dark:bg-sky-950/30 text-center ring-1 ring-sky-500/10">
            <span class="text-xs text-sky-600 dark:text-sky-400">{{ __('general.status_substituted') }}</span>
            <p class="text-xl font-bold text-sky-700 dark:text-sky-300 mt-1">{{ $substitutedCount }}</p>
        </div>
        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 text-center ring-1 ring-rose-500/10">
            <span class="text-xs text-rose-600 dark:text-rose-400">{{ __('general.status_cancelled') }}</span>
            <p class="text-xl font-bold text-rose-700 dark:text-rose-300 mt-1">{{ $cancelledCount }}</p>
        </div>
        <div class="p-3 rounded-lg bg-indigo-50 dark:bg-indigo-950/30 text-center ring-1 ring-indigo-500/10 col-span-2 sm:col-span-4 lg:col-span-1">
            <span class="text-xs text-indigo-600 dark:text-indigo-400">{{ __('general.total_taught_hours') }}</span>
            <p class="text-xl font-bold text-indigo-700 dark:text-indigo-300 mt-1">
                {{ number_format($totalActualHours, 1) }} / {{ number_format($totalPlannedHours, 1) }} {{ __('general.hours_short') }}
            </p>
        </div>
    </div>

    <!-- Sessions List / Table -->
    @if($sessions->isEmpty())
        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-academic-cap class="w-12 h-12 mx-auto text-gray-400 mb-3 opacity-60" />
            <p>{{ __('general.no_sessions_recorded') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full text-right text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="py-3 px-4 text-right">{{ __('general.date') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('general.teacher') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('general.status') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('general.actual_hours') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('general.notes') }} / {{ __('general.cancellation_reason') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('general.details') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
                    @foreach($sessions as $session)
                        @php
                            $isSubstituted = $session->status === 'substituted' || 
                                ($session->actual_teacher_id && $session->primary_teacher_id && (int)$session->actual_teacher_id !== (int)$session->primary_teacher_id);
                        @endphp
                        <tr x-data="{ open: false }" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                <div>{{ \Carbon\Carbon::parse($session->date)->translatedFormat('l') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($session->date)->format('d/m/Y') }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    {{ $session->actualTeacher?->name ?? '—' }}
                                </div>
                                @if($isSubstituted)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        {{ __('general.status_substituted') }} ({{ __('general.primary_teacher') }}: {{ $session->primaryTeacher?->name ?? '—' }})
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @switch($session->status)
                                    @case('completed')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            {{ __('general.status_completed') }}
                                        </span>
                                        @break
                                    @case('substituted')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300">
                                            {{ __('general.status_substituted') }}
                                        </span>
                                        @break
                                    @case('cancelled')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                            {{ __('general.status_cancelled') }}
                                        </span>
                                        @break
                                    @case('postponed')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            {{ __('general.status_postponed') }}
                                        </span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $session->status }}
                                        </span>
                                @endswitch
                            </td>
                            <td class="py-3 px-4 font-mono font-semibold text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                {{ number_format((float)$session->actual_hours, 1) }} / {{ number_format((float)$session->planned_hours, 1) }} {{ __('general.hours_short') }}
                            </td>
                            <td class="py-3 px-4 max-w-xs truncate text-gray-600 dark:text-gray-400">
                                @if($session->status === 'cancelled' && $session->cancellation_reason)
                                    <span class="text-rose-600 dark:text-rose-400 font-medium">{{ $session->cancellation_reason }}</span>
                                @else
                                    {{ $session->notes ?? '—' }}
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <button 
                                    type="button" 
                                    @click="open = !open" 
                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 focus:outline-none"
                                >
                                    <span x-text="open ? '{{ __('general.close') }}' : '{{ __('general.details') }}'"></span>
                                    <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform" ::class="open ? 'rotate-180' : ''" />
                                </button>
                            </td>
                        </tr>
                        <!-- Day Detail Row -->
                        <tr x-show="open" x-cloak class="bg-gray-50/80 dark:bg-gray-800/50 border-t border-b border-gray-200 dark:border-gray-700">
                            <td colspan="6" class="p-4">
                                <div class="rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xs border border-gray-200 dark:border-gray-700 space-y-3">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <x-heroicon-o-information-circle class="w-4 h-4 text-primary-500" />
                                        {{ __('general.session_details') }} — {{ \Carbon\Carbon::parse($session->date)->translatedFormat('l d/m/Y') }}
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">{{ __('general.primary_teacher') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-white ml-1">{{ $session->primaryTeacher?->name ?? '—' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">{{ __('general.actual_teacher') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-white ml-1">{{ $session->actualTeacher?->name ?? '—' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">{{ __('general.planned_hours') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-white ml-1">{{ number_format((float)$session->planned_hours, 2) }} {{ __('general.hours_short') }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 dark:text-gray-400">{{ __('general.actual_hours') }}:</span>
                                            <span class="font-semibold text-gray-900 dark:text-white ml-1">{{ number_format((float)$session->actual_hours, 2) }} {{ __('general.hours_short') }}</span>
                                        </div>
                                        @if($session->createdBy)
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">{{ __('general.created_at') }}:</span>
                                                <span class="font-semibold text-gray-900 dark:text-white ml-1">{{ $session->createdBy->name }} ({{ $session->created_at?->format('d/m/Y H:i') }})</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if($session->cancellation_reason)
                                        <div class="text-xs pt-1">
                                            <span class="text-rose-600 font-semibold">{{ __('general.cancellation_reason') }}:</span>
                                            <p class="text-gray-800 dark:text-gray-200 mt-0.5 bg-rose-50 dark:bg-rose-950/30 p-2 rounded border border-rose-200 dark:border-rose-900/50">{{ $session->cancellation_reason }}</p>
                                        </div>
                                    @endif
                                    @if($session->notes)
                                        <div class="text-xs pt-1">
                                            <span class="text-gray-500 dark:text-gray-400 font-semibold">{{ __('general.notes') }}:</span>
                                            <p class="text-gray-800 dark:text-gray-200 mt-0.5 bg-gray-50 dark:bg-gray-800/80 p-2 rounded border border-gray-200 dark:border-gray-700">{{ $session->notes }}</p>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
