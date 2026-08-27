@php
    $today = \Carbon\Carbon::today()->format('Y-m-d');
@endphp
<div class="space-y-4" x-data="{ 
    searchDate: '',
    viewTitle: '',
    viewContent: '',
    actionType: '',
    actionArgs: {},
    noteText: '',
    reasonText: '',
    showError: false,
    openReason(type, args, currentNote = '', currentReason = '') {
        this.actionType = type;
        this.actionArgs = args;
        this.noteText = currentNote;
        this.reasonText = currentReason;
        this.showError = false;
        $dispatch('open-modal', { id: 'reason-modal' });
    },
    openView(title, content) {
        this.viewTitle = title;
        this.viewContent = content;
        $dispatch('open-modal', { id: 'view-modal' });
    },
    submitReason() {
        if (this.actionType === 'mark' && !this.reasonText.trim()) {
            this.showError = true;
            return;
        }
        if (this.actionType === 'mark') {
            @this.call('markAttendance', this.actionArgs.registration_id, this.actionArgs.date, this.actionArgs.status, this.noteText, this.reasonText);
        } else if (this.actionType === 'edit') {
            @this.call('editAttendanceNoteAlpine', this.actionArgs.record_id, this.noteText, this.reasonText);
        }
        $dispatch('close-modal', { id: 'reason-modal' });
    }
}">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4 text-sm">
        <div>
            <strong>{{ __('general.student') }}:</strong> {{ $registration->student->name }}<br>
            <strong>{{ __('general.batch') }}:</strong> {{ $registration->batch->name }}
        </div>
        <div class="w-full sm:w-auto">
            <input 
                type="date" 
                x-model="searchDate" 
                class="w-full sm:w-auto border-gray-300 dark:border-white/10 dark:bg-white/5 rounded-lg shadow-sm text-sm" 
            >
        </div>
    </div>

    <div class="overflow-y-auto max-h-[60vh] border border-gray-200 dark:border-white/10 rounded-xl">
        <table class="w-full text-sm text-left rtl:text-right relative">
            <thead class="bg-gray-50 dark:bg-white/5 sticky top-0 z-10 shadow-sm">
                <tr>
                    <th class="px-4 py-2">{{ __('general.date') }}</th>
                    <th class="px-4 py-2">{{ __('general.status') }}</th>
                    <th class="px-4 py-2">{{ __('general.notes') }}</th>
                    <th class="px-4 py-2">{{ __('general.change_reason') }}</th>
                    <th class="px-4 py-2">{{ __('general.last_changed_by') }}</th>
                    <th class="px-4 py-2">{{ __('general.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse($validDates as $date)
                    @php
                        $record = $attendanceMap[$date] ?? null;
                        $status = $record['status'] ?? null;
                        
                        $statusColor = match($status) {
                            'present' => 'bg-success-500/10 text-success-600 dark:text-success-400',
                            'absent' => 'bg-danger-500/10 text-danger-600 dark:text-danger-400',
                            'late' => 'bg-warning-500/10 text-warning-600 dark:text-warning-400',
                            'excused' => 'bg-gray-500/10 text-gray-600 dark:text-gray-400',
                            default => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                        };
                        $statusLabel = $status ? __("general.attendance_status_{$status}") : __('general.unmarked');
                    @endphp
                    <tr x-show="!searchDate || searchDate === '{{ $date }}'">
                        <td class="px-4 py-3 whitespace-nowrap" dir="ltr">
                            {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $statusColor }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-[10px] text-gray-500 dark:text-gray-400 w-24 max-w-[6rem] align-middle">
                            @if(!empty($record['note']))
                                <div 
                                    class="truncate cursor-pointer hover:underline text-primary-600 dark:text-primary-400" 
                                    @click="openView('{{ __('general.notes') }}', '{{ addslashes($record['note']) }}')" 
                                    title="{{ __('general.click_to_expand') ?? 'Click to expand' }}"
                                >
                                    {{ \Illuminate\Support\Str::limit($record['note'], 15) }}
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[10px] text-gray-500 dark:text-gray-400 w-24 max-w-[6rem] align-middle">
                            @if(!empty($record['change_reason']))
                                <div 
                                    class="truncate cursor-pointer hover:underline text-primary-600 dark:text-primary-400" 
                                    @click="openView('{{ __('general.change_reason') }}', '{{ addslashes($record['change_reason']) }}')" 
                                    title="{{ __('general.click_to_expand') ?? 'Click to expand' }}"
                                >
                                    {{ \Illuminate\Support\Str::limit($record['change_reason'], 15) }}
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            @if(!empty($record['corrected_by_name']))
                                <div>{{ $record['corrected_by_name'] }}</div>
                                <div class="text-[10px] opacity-75">{{ $record['corrected_at'] }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-3 flex-wrap items-center">
                                @foreach(['present', 'absent', 'late', 'excused'] as $opt)
                                    @if($status !== $opt)
                                        <button 
                                            type="button" 
                                            class="text-xs font-medium hover:underline text-primary-600 dark:text-primary-400"
                                            @if($date !== $today && $status !== null)
                                                @click="openReason('mark', { registration_id: {{ $registration->id }}, date: '{{ $date }}', status: '{{ $opt }}' })"
                                            @else
                                                wire:click="markAttendance({{ $registration->id }}, '{{ $date }}', '{{ $opt }}')"
                                            @endif
                                        >
                                            {{ __("general.attendance_status_{$opt}") }}
                                        </button>
                                    @endif
                                @endforeach
                                
                                @if(!empty($record['record_id']))
                                    <div class="w-px h-3 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                    <button 
                                        type="button" 
                                        class="text-xs font-medium hover:underline text-gray-500 dark:text-gray-400"
                                        @click="openReason('edit', { record_id: {{ $record['record_id'] }} }, '{{ addslashes($record['note'] ?? '') }}', '{{ addslashes($record['change_reason'] ?? '') }}')"
                                    >
                                        {{ __('general.edit_note') }}
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('general.no_dates_found') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Filament Native Modal for Edit/Reason -->
    <x-filament::modal id="reason-modal" width="2xl">
        <x-slot name="heading">
            <span x-text="actionType === 'edit' ? '{{ __('general.edit_note') }}' : '{{ __('general.attendance_change_reason') }}'"></span>
        </x-slot>
        
        <div class="mt-2 relative" x-show="actionType === 'mark'">
            <label class="block text-sm font-medium leading-6 text-gray-950 dark:text-white mb-2">{{ __('general.change_reason') }}</label>
            <textarea 
                x-model="reasonText" 
                @input="showError = false"
                :class="showError ? 'border-danger-500 focus:ring-danger-500 ring-danger-500' : 'border-gray-300 dark:border-white/10 focus:ring-primary-500 focus:border-primary-500 ring-gray-950/10 dark:ring-white/20'"
                class="block w-full rounded-lg border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset bg-white dark:bg-white/5 dark:text-white sm:text-sm sm:leading-6 transition-colors duration-200" 
                rows="7" 
                style="height: 12rem; resize: none;"
                maxlength="255"
                placeholder="{{ __('general.change_reason') }}..."
                x-bind:required="actionType === 'mark'"
            ></textarea>
            <p x-show="showError && actionType === 'mark'" class="mt-2 text-sm font-medium text-danger-600 dark:text-danger-400 animate-pulse">
                {{ __('general.field_required') }}
            </p>
        </div>
        
        <div class="mt-2 relative" x-show="actionType === 'edit'">
            <label class="block text-sm font-medium leading-6 text-gray-950 dark:text-white mb-2">{{ __('general.notes') }}</label>
            <textarea 
                x-model="noteText" 
                class="block w-full rounded-lg border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-950/10 dark:ring-white/20 focus:ring-2 focus:ring-inset focus:ring-primary-500 bg-white dark:bg-white/5 dark:text-white sm:text-sm sm:leading-6 transition-colors duration-200" 
                rows="7" 
                style="height: 12rem; resize: none;"
                maxlength="255"
                placeholder="{{ __('general.notes') }}..."
            ></textarea>
        </div>

        <x-slot name="footer">
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'reason-modal' })">
                    {{ __('general.cancel') }}
                </x-filament::button>
                <x-filament::button color="primary" x-on:click="submitReason()">
                    {{ __('general.save') }}
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <!-- Filament Native Modal for Viewing Full Text -->
    <x-filament::modal id="view-modal" width="2xl">
        <x-slot name="heading">
            <span x-text="viewTitle"></span>
        </x-slot>
        
        <div class="p-6 mt-2 bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 h-48 overflow-y-auto overflow-x-hidden flex items-start shadow-inner">
            <div class="text-sm leading-relaxed whitespace-pre-wrap break-words text-gray-950 dark:text-white font-medium w-full" x-text="viewContent">
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'view-modal' })">
                    {{ __('general.close') }}
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>
</div>
