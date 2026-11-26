<x-filament-widgets::widget wire:poll.15s>
    <x-filament::section :heading="'Today\'s schedule - '.$dateLabel">
        @if (! $semester)
            <p class="text-sm text-gray-600 dark:text-gray-300">Select a semester to view its schedule for today.</p>
        @elseif ($sessions === [])
            <div class="rounded-lg border border-dashed border-gray-300 p-5 text-center dark:border-gray-700">
                <p class="font-medium text-gray-950 dark:text-white">No sessions scheduled today</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">There are no effective slots for today in {{ $semester->name }}.</p>
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($sessions as $session)
                    @php
                        $slot = $session['slot'];
                        $status = $session['record']?->status?->value;
                    @endphp
                    <div class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="min-w-28 font-semibold text-gray-950 dark:text-white">
                                {{ \Carbon\CarbonImmutable::parse($slot->start_time)->format('H:i') }} - {{ \Carbon\CarbonImmutable::parse($slot->end_time)->format('H:i') }}
                            </span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $slot->subject->name }}</span>
                            @if ($slot->subject->code)
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $slot->subject->code }}</span>
                            @endif
                            @if ($slot->label)
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $slot->label }}</span>
                            @endif
                        </div>
                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium {{ $status === 'present' ? 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-300' : ($status === 'absent' ? 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300') }}">
                            {{ $status ? ucfirst($status) : 'Not marked' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
