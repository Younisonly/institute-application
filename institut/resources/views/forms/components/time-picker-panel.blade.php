@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $labels = \Illuminate\Support\Js::from([
        'am' => __('general.am_abbr'),
        'pm' => __('general.pm_abbr'),
        'hour' => __('general.hour_placeholder'),
        'minute' => __('general.minute_placeholder'),
        'clock' => __('general.time_picker'),
        'up' => __('general.increase'),
        'down' => __('general.decrease'),
    ])->toHtml();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            hour: '',
            minute: '',
            period: 'am',
            focused: 'hour',
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            labels: {{ $labels }},
            init() {
                if (this.state && /^\d{2}:\d{2}/.test(this.state)) {
                    const hour24 = parseInt(this.state.slice(0, 2), 10);

                    this.hour = String(hour24 % 12 || 12);
                    this.minute = this.state.slice(3, 5);
                    this.period = hour24 < 12 ? 'am' : 'pm';
                }
            },
            digits(value) {
                return String(value).replace(/\D/g, '').slice(0, 2);
            },
            clamp(value, max) {
                let num = parseInt(value || '0', 10);
                if (isNaN(num)) num = 0;
                return String(Math.max(0, Math.min(num, max)));
            },
            setHour(value) {
                this.hour = this.clamp(this.digits(value), 12);
                this.focused = 'hour';
                this.writeState();

                if (this.hour.length === 2) {
                    this.focused = 'minute';
                    this.$nextTick(() => this.$refs.minuteInput.focus());
                }
            },
            setMinute(value) {
                this.minute = this.clamp(this.digits(value), 59);
                this.focused = 'minute';
                this.writeState();

                if (this.minute.length === 2) {
                    this.$nextTick(() => this.$refs.minuteInput.blur());
                }
            },
            setPeriod(period) {
                this.period = period;
                this.writeState();
            },
            step(delta) {
                if (this.focused === 'minute') {
                    let m = parseInt(this.minute || '0', 10);
                    m = Math.max(0, Math.min(59, m + delta));
                    this.minute = String(m).padStart(2, '0');
                } else {
                    let h = parseInt(this.hour || '0', 10) % 12;
                    h = ((h + delta) % 12 + 12) % 12 || 12;
                    this.hour = String(h);
                }

                this.writeState();
            },
            normalize() {
                if (this.hour !== '' && this.hour.length === 1) {
                    this.hour = this.hour.padStart(2, '0');
                }

                if (this.minute !== '' && this.minute.length === 1) {
                    this.minute = this.minute.padStart(2, '0');
                }

                this.writeState();
            },
            writeState() {
                const hour = parseInt(this.hour, 10);
                const minute = parseInt(this.minute, 10);

                if (isNaN(hour) || isNaN(minute)) {
                    return;
                }

                const hour24 = hour % 12 + (this.period === 'pm' ? 12 : 0);

                this.state = `${String(hour24).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
            },
        }"
        class="w-fit max-w-full"
    >
        <div dir="ltr" class="flex flex-wrap items-center gap-2">
            <div
                class="flex h-11 items-center gap-1 rounded-xl bg-white p-1 shadow-sm ring-1 ring-gray-950/10 transition duration-75 focus-within:ring-2 focus-within:ring-primary-500 disabled:opacity-50 dark:bg-white/5 dark:ring-white/10"
            >
                <span class="hidden select-none pl-1 text-gray-400 sm:block" :class="{ 'text-gray-400': true }" aria-hidden="true">
                    <x-heroicon-o-clock class="h-5 w-5" />
                </span>

                <div class="flex h-9 flex-col overflow-hidden rounded-lg" aria-hidden="true">
                    <button
                        type="button"
                        tabindex="-1"
                        x-on:click="step(1)"
                        :disabled="$isDisabled"
                        :aria-label="labels.up"
                        class="flex h-1/2 w-7 items-center justify-center rounded-md text-gray-400 transition duration-75 hover:bg-primary-50 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-50 dark:hover:bg-primary-500/10 dark:hover:text-primary-400"
                    >
                        <x-heroicon-m-chevron-up class="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        tabindex="-1"
                        x-on:click="step(-1)"
                        :disabled="$isDisabled"
                        :aria-label="labels.down"
                        class="flex h-1/2 w-7 items-center justify-center rounded-md text-gray-400 transition duration-75 hover:bg-primary-50 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-50 dark:hover:bg-primary-500/10 dark:hover:text-primary-400"
                    >
                        <x-heroicon-m-chevron-down class="h-3.5 w-3.5" />
                    </button>
                </div>

                <input
                    x-ref="hourInput"
                    type="text"
                    inputmode="numeric"
                    maxlength="2"
                    x-model="hour"
                    x-on:input="setHour($event.target.value)"
                    x-on:focus="$el.select(); focused = 'hour'"
                    x-on:blur="normalize()"
                    x-on:keydown.arrow-up.prevent="step(1)"
                    x-on:keydown.arrow-down.prevent="step(-1)"
                    :disabled="$isDisabled"
                    :placeholder="labels.hour"
                    :aria-label="labels.hour"
                    class="h-full w-14 min-w-14 rounded-lg px-1 text-center text-base font-semibold tabular-nums text-gray-950 outline-none transition duration-75 placeholder:text-sm placeholder:font-normal placeholder:text-gray-400 hover:bg-gray-50 focus:bg-primary-50/50 disabled:text-gray-500 dark:text-white dark:placeholder:text-gray-500 dark:hover:bg-white/5 dark:focus:bg-primary-500/10 [field-sizing:content]"
                />

                <span class="select-none px-0.5 text-base font-semibold text-gray-300 dark:text-gray-600">:</span>

                <input
                    x-ref="minuteInput"
                    type="text"
                    inputmode="numeric"
                    maxlength="2"
                    x-model="minute"
                    x-on:input="setMinute($event.target.value)"
                    x-on:focus="$el.select(); focused = 'minute'"
                    x-on:blur="normalize()"
                    x-on:keydown.arrow-up.prevent="step(1)"
                    x-on:keydown.arrow-down.prevent="step(-1)"
                    :disabled="$isDisabled"
                    :placeholder="labels.minute"
                    :aria-label="labels.minute"
                    class="h-full w-14 min-w-14 rounded-lg px-1 text-center text-base font-semibold tabular-nums text-gray-950 outline-none transition duration-75 placeholder:text-sm placeholder:font-normal placeholder:text-gray-400 hover:bg-gray-50 focus:bg-primary-50/50 disabled:text-gray-500 dark:text-white dark:placeholder:text-gray-500 dark:hover:bg-white/5 dark:focus:bg-primary-500/10 [field-sizing:content]"
                />
            </div>

            <div
                class="flex h-11 shrink-0 items-center gap-1 rounded-xl bg-gray-100 p-1 shadow-sm ring-1 ring-gray-950/5 disabled:opacity-50 dark:bg-white/10 dark:ring-white/10"
            >
                <button
                    type="button"
                    x-on:click="setPeriod('am')"
                    x-text="labels.am"
                    :disabled="$isDisabled"
                    :class="period === 'am' ? 'bg-white text-gray-950 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/10 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="h-9 rounded-lg px-3 text-sm font-bold tracking-wide transition duration-75 disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                ></button>
                <button
                    type="button"
                    x-on:click="setPeriod('pm')"
                    x-text="labels.pm"
                    :disabled="$isDisabled"
                    :class="period === 'pm' ? 'bg-white text-gray-950 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/10 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="h-9 rounded-lg px-3 text-sm font-bold tracking-wide transition duration-75 disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                ></button>
            </div>
        </div>
    </div>
</x-dynamic-component>