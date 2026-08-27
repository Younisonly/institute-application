<x-filament-widgets::widget>
    <div class="fi-section overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-200 p-4 dark:border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                {{ __('general.recent_activity') }}
            </h3>
            <x-filament::icon
                icon="heroicon-m-clock"
                class="h-5 w-5 text-gray-400 dark:text-gray-500"
            />
        </div>
        
        <div class="p-4">
            <div class="relative border-s-2 border-gray-200 dark:border-gray-800 ms-3">
                @forelse ($this->getLogs() as $log)
                    @php
                        $actionKey = 'general.' . $log->action;
                        $actionLabel = \Illuminate\Support\Facades\Lang::has($actionKey) ? __($actionKey) : $log->action;
                        
                        $entityLabel = '';
                        if ($log->entity_type) {
                            $base = class_basename($log->entity_type);
                            $entityKey = 'general.entity_' . $base;
                            $entityLabel = \Illuminate\Support\Facades\Lang::has($entityKey) ? __($entityKey) : $base;
                            $entityLabel .= ' #' . $log->entity_id;
                        }
                        
                        $icon = match(true) {
                            str_contains($log->action, 'created') || str_contains($log->action, 'opened') || str_contains($log->action, 'active') => 'heroicon-m-plus-circle',
                            str_contains($log->action, 'completed') || str_contains($log->action, 'issued') => 'heroicon-m-check-circle',
                            str_contains($log->action, 'voided') || str_contains($log->action, 'cancelled') || str_contains($log->action, 'suspended') => 'heroicon-m-x-circle',
                            default => 'heroicon-m-information-circle'
                        };
                        
                        $color = match(true) {
                            str_contains($log->action, 'created') || str_contains($log->action, 'opened') || str_contains($log->action, 'active') => 'text-success-500',
                            str_contains($log->action, 'completed') || str_contains($log->action, 'issued') => 'text-primary-500',
                            str_contains($log->action, 'voided') || str_contains($log->action, 'cancelled') || str_contains($log->action, 'suspended') => 'text-danger-500',
                            default => 'text-gray-500'
                        };
                    @endphp
                    <div class="mb-6 ms-6 last:mb-0">
                        <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white ring-4 ring-white dark:bg-gray-900 dark:ring-gray-900">
                            <x-filament::icon :icon="$icon" class="h-5 w-5 {{ $color }}" />
                        </span>
                        
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <h4 class="text-sm font-medium text-gray-950 dark:text-white">
                                {{ $actionLabel }} 
                                @if($entityLabel)
                                    <span class="text-gray-500 dark:text-gray-400 font-normal">({{ $entityLabel }})</span>
                                @endif
                            </h4>
                            <time class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $log->created_at?->diffForHumans() }}
                            </time>
                        </div>
                        
                        @if($log->user)
                        <div class="mt-1 flex items-center gap-2">
                            <x-filament::avatar
                                :src="null"
                                :alt="$log->user->name"
                                size="sm"
                                class="h-5 w-5"
                            />
                            <p class="text-xs text-gray-600 dark:text-gray-300">
                                {{ $log->user->name }}
                            </p>
                        </div>
                        @endif
                    </div>
                @empty
                    <div class="ms-6 text-sm text-gray-500 dark:text-gray-400 py-4">
                        {{ __('general.no_recent_activity') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-widgets::widget>