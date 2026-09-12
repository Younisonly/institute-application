<div x-data="{
    search: '',
    statusFilter: 'all',
    openSessionId: null,
    toggleSession(id) {
        this.openSessionId = this.openSessionId === id ? null : id;
    }
}" class="space-y-6 text-gray-900 dark:text-gray-100 antialiased font-sans">

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

        $totalActualHours = (float) $sessions->sum('actual_hours');
        $totalPlannedHours = (float) $sessions->sum('planned_hours');
        $completionPercentage = $totalPlannedHours > 0 
            ? min(100, round(($totalActualHours / $totalPlannedHours) * 100, 1))
            : ($totalDays > 0 ? min(100, round(($completedCount / $totalDays) * 100, 1)) : 0);
        
        $substitutionPercentage = ($completedCount + $substitutedCount) > 0
            ? round(($substitutedCount / max(1, $completedCount + $substitutedCount)) * 100, 1)
            : 0;

        $periods = $batch->periods;
    @endphp

    <!-- 1. HERO HEADER & BATCH INFO CARD (System Dark Mode Aware) -->
    <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 p-5 sm:p-6 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 transition-all">
        <!-- Ambient subtle glow background for dark/light theme -->
        <div class="absolute -top-24 -left-24 w-72 h-72 bg-primary-500/10 dark:bg-primary-500/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-72 h-72 bg-emerald-500/10 dark:bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-3 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 ring-1 ring-primary-500/20 dark:ring-primary-400/30">
                        <x-heroicon-m-academic-cap class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400" />
                        {{ $batch->course?->name ?? __('general.course') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ match($batch->status) {
                        'in_progress', 'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 ring-emerald-500/20 dark:ring-emerald-400/30',
                        'completed' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 ring-sky-500/20 dark:ring-sky-400/30',
                        'cancelled' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 ring-rose-500/20 dark:ring-rose-400/30',
                        default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 ring-amber-500/20 dark:ring-amber-400/30',
                    } }} ring-1">
                        <x-heroicon-m-sparkles class="w-3.5 h-3.5" />
                        {{ __("general.batch_status_{$batch->status}") }}
                    </span>
                </div>

                <h2 class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                    {{ $batch->name }}
                </h2>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                    <div class="inline-flex items-center gap-1.5 bg-gray-100/80 dark:bg-gray-800/70 px-3 py-1.5 rounded-lg ring-1 ring-gray-200/60 dark:ring-gray-700/60">
                        <x-heroicon-m-user class="w-4 h-4 text-primary-500 dark:text-primary-400 shrink-0" />
                        <span class="text-gray-500 dark:text-gray-400">{{ __('general.primary_teacher') }}:</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $batch->teacher?->name ?? '—' }}</span>
                    </div>

                    @if($batch->start_date || $batch->end_date)
                    <div class="inline-flex items-center gap-1.5 bg-gray-100/80 dark:bg-gray-800/70 px-3 py-1.5 rounded-lg ring-1 ring-gray-200/60 dark:ring-gray-700/60">
                        <x-heroicon-m-calendar class="w-4 h-4 text-emerald-500 dark:text-emerald-400 shrink-0" />
                        <span class="font-medium text-gray-800 dark:text-gray-200">
                            {{ $batch->start_date?->format('d/m/Y') ?? '—' }}
                            <span class="mx-1 text-gray-400">→</span>
                            {{ $batch->end_date?->format('d/m/Y') ?? '—' }}
                        </span>
                    </div>
                    @endif

                    @if($periods && $periods->isNotEmpty())
                    <div class="inline-flex items-center gap-1.5 bg-gray-100/80 dark:bg-gray-800/70 px-3 py-1.5 rounded-lg ring-1 ring-gray-200/60 dark:ring-gray-700/60">
                        <x-heroicon-m-clock class="w-4 h-4 text-amber-500 dark:text-amber-400 shrink-0" />
                        <span class="font-medium text-gray-800 dark:text-gray-200">
                            {{ $periods->pluck('name')->join(' ، ') }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Print PDF Action Button -->
            <div class="flex items-center shrink-0">
                <a 
                    href="{{ route('reports.teaching-sessions.batch.print', ['batch' => $batch]) }}" 
                    target="_blank"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold text-white bg-primary-600 hover:bg-primary-500 active:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-400 rounded-xl shadow-sm hover:shadow transition-all duration-200 w-full sm:w-auto transform hover:-translate-y-0.5"
                >
                    <x-heroicon-o-printer class="w-4 h-4 sm:w-5 sm:h-5" />
                    <span>{{ __('general.print_report') }}</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 2. ANALYTICAL STATS CARDS & PROGRESS (System Dark Theme Aware) -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 sm:gap-4">
        <!-- Stat 1: Total Sessions -->
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-900 p-4 border border-gray-200/80 dark:border-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('general.number_of_sessions') }}</span>
                <div class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                    <x-heroicon-o-calendar-days class="w-4 h-4 sm:w-5 sm:h-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white">{{ $totalDays }}</span>
                <span class="text-[11px] sm:text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('general.teaching_session') }}</span>
            </div>
        </div>

        <!-- Stat 2: Completed Sessions -->
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-900 p-4 border border-gray-200/80 dark:border-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('general.status_completed') }}</span>
                <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4 sm:w-5 sm:h-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight text-emerald-600 dark:text-emerald-400">{{ $completedCount }}</span>
                <span class="text-[11px] sm:text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/80 px-2 py-0.5 rounded-full">
                    {{ $totalDays > 0 ? round(($completedCount / $totalDays) * 100) : 0 }}%
                </span>
            </div>
        </div>

        <!-- Stat 3: Substituted Sessions -->
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-900 p-4 border border-gray-200/80 dark:border-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('general.status_substituted') }}</span>
                <div class="p-2 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                    <x-heroicon-o-arrows-right-left class="w-4 h-4 sm:w-5 sm:h-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight text-sky-600 dark:text-sky-400">{{ $substitutedCount }}</span>
                <span class="text-[11px] sm:text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ $substitutionPercentage }}% {{ __('general.substitution_ratio') }}
                </span>
            </div>
        </div>

        <!-- Stat 4: Cancelled / Postponed -->
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-900 p-4 border border-gray-200/80 dark:border-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('general.status_cancelled') }} / {{ __('general.status_postponed') }}</span>
                <div class="p-2 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 sm:w-5 sm:h-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight text-rose-600 dark:text-rose-400">
                    {{ $cancelledCount + $postponedCount }}
                </span>
                <span class="text-[11px] sm:text-xs font-medium text-gray-500 dark:text-gray-400">
                    ({{ $cancelledCount }} {{ __('general.status_cancelled') }})
                </span>
            </div>
        </div>

        <!-- Stat 5: Hours Taught & Progress Ring/Bar -->
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-900 p-4 border border-gray-200/80 dark:border-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 col-span-2 sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('general.completion_rate') }}</span>
                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/80 px-2 py-0.5 rounded-full">
                    {{ $completionPercentage }}%
                </span>
            </div>
            
            <div class="mt-1">
                <div class="flex items-baseline gap-1">
                    <span class="text-xl font-extrabold tracking-tight text-indigo-600 dark:text-indigo-400">
                        {{ number_format($totalActualHours, 1) }}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        / {{ number_format($totalPlannedHours, 1) }} {{ __('general.hours_short') }}
                    </span>
                </div>
                <!-- Progress bar -->
                <div class="w-full h-2 bg-gray-100 dark:bg-gray-800 rounded-full mt-2 overflow-hidden">
                    <div 
                        class="h-full bg-gradient-to-r from-primary-500 to-indigo-600 dark:from-primary-400 dark:to-indigo-500 rounded-full transition-all duration-500" 
                        style="width: {{ $completionPercentage }}%"
                    ></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. INTERACTIVE SEARCH & FILTER CONTROLS -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3 rounded-xl bg-gray-50/80 dark:bg-gray-800/60 border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
        
        <!-- Status Pills Filter -->
        <div class="flex flex-wrap items-center gap-1 overflow-x-auto pb-1 sm:pb-0">
            <button 
                type="button" 
                @click="statusFilter = 'all'"
                :class="statusFilter === 'all' 
                    ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-xs ring-1 ring-gray-950/5 dark:ring-white/10 font-bold' 
                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-700/60 font-medium'"
                class="px-3 py-1.5 text-xs rounded-lg transition-all duration-150 whitespace-nowrap"
            >
                {{ __('general.all') }} ({{ $totalDays }})
            </button>

            <button 
                type="button" 
                @click="statusFilter = 'completed'"
                :class="statusFilter === 'completed' 
                    ? 'bg-emerald-500 text-white shadow-xs font-bold' 
                    : 'text-gray-600 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 font-medium'"
                class="px-3 py-1.5 text-xs rounded-lg transition-all duration-150 whitespace-nowrap"
            >
                {{ __('general.status_completed') }} ({{ $completedCount }})
            </button>

            <button 
                type="button" 
                @click="statusFilter = 'substituted'"
                :class="statusFilter === 'substituted' 
                    ? 'bg-sky-500 text-white shadow-xs font-bold' 
                    : 'text-gray-600 dark:text-gray-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40 font-medium'"
                class="px-3 py-1.5 text-xs rounded-lg transition-all duration-150 whitespace-nowrap"
            >
                {{ __('general.status_substituted') }} ({{ $substitutedCount }})
            </button>

            <button 
                type="button" 
                @click="statusFilter = 'cancelled'"
                :class="statusFilter === 'cancelled' 
                    ? 'bg-rose-500 text-white shadow-xs font-bold' 
                    : 'text-gray-600 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 font-medium'"
                class="px-3 py-1.5 text-xs rounded-lg transition-all duration-150 whitespace-nowrap"
            >
                {{ __('general.status_cancelled') }} ({{ $cancelledCount }})
            </button>

            @if($postponedCount > 0)
            <button 
                type="button" 
                @click="statusFilter = 'postponed'"
                :class="statusFilter === 'postponed' 
                    ? 'bg-amber-500 text-white shadow-xs font-bold' 
                    : 'text-gray-600 dark:text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 font-medium'"
                class="px-3 py-1.5 text-xs rounded-lg transition-all duration-150 whitespace-nowrap"
            >
                {{ __('general.status_postponed') }} ({{ $postponedCount }})
            </button>
            @endif
        </div>

        <!-- Live Search Box -->
        <div class="relative flex-1 sm:max-w-xs">
            <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input 
                type="text" 
                x-model="search" 
                placeholder="{{ __('general.search_sessions_placeholder') }}" 
                class="w-full pr-9 pl-3 py-1.5 text-xs rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:focus:ring-primary-400/50 transition-all placeholder:text-gray-400 dark:placeholder:text-gray-500"
            />
        </div>
    </div>

    <!-- 4. SESSIONS LIST TABLE / CARDS -->
    @if($sessions->isEmpty())
        <div class="text-center py-16 px-4 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="w-16 h-16 mx-auto rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 mb-4">
                <x-heroicon-o-academic-cap class="w-8 h-8" />
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('general.no_sessions_recorded') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">لم يتم تسجِيل أي جلسة تدريس لهذه الدفعة بعد.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50/90 dark:bg-gray-800/80 text-gray-600 dark:text-gray-300 border-b border-gray-200/80 dark:border-gray-700/80 text-xs font-bold uppercase tracking-wider">
                            <th class="py-3.5 px-4 text-center w-12">#</th>
                            <th class="py-3.5 px-4 text-right">{{ __('general.date') }}</th>
                            <th class="py-3.5 px-4 text-right">{{ __('general.teacher') }}</th>
                            <th class="py-3.5 px-4 text-center">{{ __('general.status') }}</th>
                            <th class="py-3.5 px-4 text-center">{{ __('general.actual_hours') }}</th>
                            <th class="py-3.5 px-4 text-right">{{ __('general.notes') }} / {{ __('general.cancellation_reason') }}</th>
                            <th class="py-3.5 px-4 text-center w-24">{{ __('general.details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 bg-white dark:bg-gray-900">
                        @foreach($sessions as $index => $session)
                            @php
                                $isSubstituted = $session->status === 'substituted' || 
                                    ($session->actual_teacher_id && $session->primary_teacher_id && (int)$session->actual_teacher_id !== (int)$session->primary_teacher_id);
                                $searchHaystack = mb_strtolower(
                                    $session->date . ' ' . 
                                    \Carbon\Carbon::parse($session->date)->translatedFormat('l d/m/Y') . ' ' . 
                                    ($session->actualTeacher?->name ?? '') . ' ' . 
                                    ($session->primaryTeacher?->name ?? '') . ' ' . 
                                    ($session->notes ?? '') . ' ' . 
                                    ($session->cancellation_reason ?? '')
                                );
                            @endphp

                            <tr 
                                x-show="(statusFilter === 'all' || statusFilter === @json($session->status) || (statusFilter === 'substituted' && {{ $isSubstituted ? 'true' : 'false' }})) && (search === '' || @json($searchHaystack).includes(search.toLowerCase()))"
                                class="hover:bg-gray-50/70 dark:hover:bg-white/[0.03] transition-colors duration-150 group"
                            >
                                <!-- Index -->
                                <td class="py-3.5 px-4 text-center font-mono text-xs text-gray-400 dark:text-gray-500 font-medium">
                                    {{ $index + 1 }}
                                </td>

                                <!-- Date & Day -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <x-heroicon-m-calendar class="w-4 h-4 text-gray-400 dark:text-gray-500 group-hover:text-primary-500 transition-colors" />
                                        <span>{{ \Carbon\Carbon::parse($session->date)->translatedFormat('l') }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-0.5">
                                        {{ \Carbon\Carbon::parse($session->date)->format('d/m/Y') }}
                                    </div>
                                </td>

                                <!-- Actual Teacher (With substitution indicator) -->
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <x-heroicon-m-user-circle class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                        <span>{{ $session->actualTeacher?->name ?? '—' }}</span>
                                    </div>
                                    @if($isSubstituted)
                                        <div class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 ring-1 ring-amber-500/20 dark:ring-amber-400/30">
                                            <x-heroicon-m-arrows-right-left class="w-3 h-3 text-amber-600 dark:text-amber-400 shrink-0" />
                                            <span>{{ __('general.status_substituted') }}</span>
                                            <span class="text-amber-600/70 dark:text-amber-400/70">({{ __('general.primary_teacher') }}: {{ $session->primaryTeacher?->name ?? '—' }})</span>
                                        </div>
                                    @endif
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    @switch($session->status)
                                        @case('completed')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 ring-1 ring-emerald-500/20 dark:ring-emerald-400/30">
                                                <x-heroicon-m-check-circle class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                                {{ __('general.status_completed') }}
                                            </span>
                                            @break
                                        @case('substituted')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-sky-50 text-sky-700 dark:bg-sky-950/70 dark:text-sky-300 ring-1 ring-sky-500/20 dark:ring-sky-400/30">
                                                <x-heroicon-m-arrows-right-left class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" />
                                                {{ __('general.status_substituted') }}
                                            </span>
                                            @break
                                        @case('cancelled')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-rose-50 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300 ring-1 ring-rose-500/20 dark:ring-rose-400/30">
                                                <x-heroicon-m-x-circle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                                                {{ __('general.status_cancelled') }}
                                            </span>
                                            @break
                                        @case('postponed')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 ring-1 ring-amber-500/20 dark:ring-amber-400/30">
                                                <x-heroicon-m-clock class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                                {{ __('general.status_postponed') }}
                                            </span>
                                            @break
                                        @default
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 ring-1 ring-gray-200 dark:ring-gray-700">
                                                {{ __("general.status_{$session->status}") }}
                                            </span>
                                    @endswitch
                                </td>

                                <!-- Actual / Planned Hours -->
                                <td class="py-3.5 px-4 text-center font-mono text-xs whitespace-nowrap">
                                    <span class="font-extrabold text-gray-900 dark:text-white">{{ number_format((float)$session->actual_hours, 1) }}</span>
                                    <span class="text-gray-400 dark:text-gray-500">/ {{ number_format((float)$session->planned_hours, 1) }} {{ __('general.hours_short') }}</span>
                                </td>

                                <!-- Notes / Cancellation Reason -->
                                <td class="py-3.5 px-4 max-w-xs text-xs text-gray-600 dark:text-gray-300">
                                    @if($session->status === 'cancelled' && $session->cancellation_reason)
                                        <div class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-bold bg-rose-50 dark:bg-rose-950/40 px-2 py-1 rounded-md ring-1 ring-rose-200 dark:ring-rose-900/40">
                                            <x-heroicon-m-exclamation-circle class="w-3.5 h-3.5 shrink-0" />
                                            <span class="truncate">{{ $session->cancellation_reason }}</span>
                                        </div>
                                    @elseif($session->notes)
                                        <div class="truncate text-gray-700 dark:text-gray-300 bg-gray-100/70 dark:bg-gray-800/60 px-2 py-1 rounded-md">
                                            {{ $session->notes }}
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                                <!-- Details Expand Toggle -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <button 
                                        type="button" 
                                        @click="toggleSession({{ $session->id }})" 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-primary-600 hover:text-primary-700 bg-primary-50 hover:bg-primary-100 dark:text-primary-400 dark:hover:text-primary-300 dark:bg-primary-950/50 dark:hover:bg-primary-950/80 transition-all focus:outline-none"
                                    >
                                        <span x-show="openSessionId === {{ $session->id }}">{{ __('general.close') }}</span>
                                        <span x-show="openSessionId !== {{ $session->id }}">{{ __('general.details') }}</span>
                                        <x-heroicon-m-chevron-down class="w-3.5 h-3.5 transition-transform duration-200" ::class="openSessionId === {{ $session->id }} ? 'rotate-180' : ''" />
                                    </button>
                                </td>
                            </tr>

                            <!-- Expanded Detail Row -->
                            <tr 
                                x-show="openSessionId === {{ $session->id }}" 
                                x-cloak 
                                transition:enter="transition ease-out duration-200"
                                transition:enter-start="opacity-0 -translate-y-1"
                                transition:enter-end="opacity-100 translate-y-0"
                                class="bg-gray-50/90 dark:bg-gray-800/40 border-t border-b border-gray-200/80 dark:border-gray-700/80"
                            >
                                <td colspan="7" class="p-4 sm:p-5">
                                    <div class="rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/80 space-y-4">
                                        
                                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                                            <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                                <x-heroicon-o-information-circle class="w-4 h-4 text-primary-500" />
                                                <span>{{ __('general.session_details') }} — {{ \Carbon\Carbon::parse($session->date)->translatedFormat('l d/m/Y') }}</span>
                                            </h4>
                                            <span class="text-xs font-mono text-gray-400 dark:text-gray-500">ID: #{{ $session->id }}</span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                                            <div class="bg-gray-50 dark:bg-gray-800/60 p-3 rounded-lg border border-gray-100 dark:border-gray-700/60">
                                                <span class="text-gray-500 dark:text-gray-400 block font-medium">{{ __('general.primary_teacher') }}</span>
                                                <span class="font-bold text-gray-900 dark:text-white text-sm mt-0.5 block">{{ $session->primaryTeacher?->name ?? '—' }}</span>
                                            </div>

                                            <div class="bg-gray-50 dark:bg-gray-800/60 p-3 rounded-lg border border-gray-100 dark:border-gray-700/60">
                                                <span class="text-gray-500 dark:text-gray-400 block font-medium">{{ __('general.actual_teacher') }}</span>
                                                <span class="font-bold text-gray-900 dark:text-white text-sm mt-0.5 block">{{ $session->actualTeacher?->name ?? '—' }}</span>
                                            </div>

                                            <div class="bg-gray-50 dark:bg-gray-800/60 p-3 rounded-lg border border-gray-100 dark:border-gray-700/60">
                                                <span class="text-gray-500 dark:text-gray-400 block font-medium">{{ __('general.planned_hours') }}</span>
                                                <span class="font-bold text-gray-900 dark:text-white text-sm mt-0.5 block font-mono">{{ number_format((float)$session->planned_hours, 2) }} {{ __('general.hours_short') }}</span>
                                            </div>

                                            <div class="bg-gray-50 dark:bg-gray-800/60 p-3 rounded-lg border border-gray-100 dark:border-gray-700/60">
                                                <span class="text-gray-500 dark:text-gray-400 block font-medium">{{ __('general.actual_hours') }}</span>
                                                <span class="font-bold text-emerald-600 dark:text-emerald-400 text-sm mt-0.5 block font-mono">{{ number_format((float)$session->actual_hours, 2) }} {{ __('general.hours_short') }}</span>
                                            </div>
                                        </div>

                                        @if($session->createdBy)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5 pt-1">
                                                <x-heroicon-m-user class="w-3.5 h-3.5" />
                                                <span>{{ __('general.created_at') }}:</span>
                                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $session->createdBy->name }}</span>
                                                <span>({{ $session->created_at?->format('d/m/Y H:i') }})</span>
                                            </div>
                                        @endif

                                        @if($session->cancellation_reason)
                                            <div class="text-xs space-y-1 bg-rose-50 dark:bg-rose-950/30 p-3 rounded-lg border border-rose-200/80 dark:border-rose-900/50">
                                                <span class="text-rose-700 dark:text-rose-300 font-bold flex items-center gap-1.5">
                                                    <x-heroicon-m-exclamation-triangle class="w-4 h-4 text-rose-600 dark:text-rose-400" />
                                                    {{ __('general.cancellation_reason') }}:
                                                </span>
                                                <p class="text-rose-900 dark:text-rose-200 pl-5 leading-relaxed">{{ $session->cancellation_reason }}</p>
                                            </div>
                                        @endif

                                        @if($session->notes)
                                            <div class="text-xs space-y-1 bg-gray-50 dark:bg-gray-800/70 p-3 rounded-lg border border-gray-200/60 dark:border-gray-700/70">
                                                <span class="text-gray-700 dark:text-gray-300 font-bold flex items-center gap-1.5">
                                                    <x-heroicon-m-document-text class="w-4 h-4 text-primary-500" />
                                                    {{ __('general.notes') }}:
                                                </span>
                                                <p class="text-gray-800 dark:text-gray-200 pl-5 leading-relaxed whitespace-pre-line">{{ $session->notes }}</p>
                                            </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
