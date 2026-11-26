<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Private Telegram connection</x-slot>
            <x-slot name="description">
                Connect your private Telegram account to receive StudyDesk reminders and interact with the study assistant.
            </x-slot>

            @if ($this->account)
                <div class="space-y-2 text-sm">
                    <p class="font-medium text-success-700 dark:text-success-400">Telegram is connected.</p>
                    <p class="text-gray-600 dark:text-gray-300">
                        Linked {{ $this->account->linked_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}
                        ({{ config('app.timezone') }}).
                    </p>
                    <p class="text-gray-600 dark:text-gray-300">
                        Subject reminders are {{ $this->account->reminders_enabled ? 'enabled' : 'paused' }}.
                    </p>
                    <p class="text-gray-600 dark:text-gray-300">
                        Weekly summary is {{ $this->account->weekly_summary_enabled ? 'enabled' : 'paused' }} for
                        {{ \App\Models\TimetableSlot::WEEKDAYS[$this->account->weekly_summary_day] ?? 'Sunday' }}
                        at {{ substr($this->account->weekly_summary_time, 0, 5) }} ({{ config('app.timezone') }}).
                    </p>
                </div>
            @else
                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <p>Generate a one-time link, then open it in Telegram's private chat with your StudyDesk bot.</p>
                    @if ($linkUrl)
                        <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                            <a href="{{ $linkUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-primary-600 underline dark:text-primary-400">
                                Open Telegram to connect
                            </a>
                            <p class="mt-2 break-all text-xs">This link expires in 10 minutes and can be used once.</p>
                        </div>
                    @endif
                    @if (blank(config('services.telegram.bot_token')) || blank(config('services.telegram.bot_username')) || blank(config('services.telegram.webhook_secret')))
                        <p class="text-warning-700 dark:text-warning-400">
                            Telegram is not configured yet. Set the bot token, bot username, and webhook secret in the environment, then reload configuration.
                        </p>
                    @endif
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Webhook setup</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Incoming bot updates are accepted at
                <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-white/10">{{ route('telegram.webhook') }}</code>.
                Telegram requires this endpoint to use a publicly reachable HTTPS URL.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
