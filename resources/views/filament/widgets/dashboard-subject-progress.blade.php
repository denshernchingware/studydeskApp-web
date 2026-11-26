<x-filament-widgets::widget wire:poll.15s>
    <x-filament::section heading="Subject performance">
        @if (! $semester)
            <p class="text-sm text-gray-600 dark:text-gray-300">Select a semester to review subject progress.</p>
        @elseif ($summaries === [])
            <div class="rounded-lg border border-dashed border-gray-300 p-5 text-center dark:border-gray-700">
                <p class="font-medium text-gray-950 dark:text-white">No subjects in this semester</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Add a subject to see target progress and attendance rates.</p>
            </div>
        @else
            <div class="space-y-5">
                @foreach ($summaries as $summary)
                    @php
                        $subject = $summary['subject'];
                        $progress = min(100, (int) round(($summary['present'] / $subject->required_sessions) * 100));
                    @endphp
                    <article class="space-y-2">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-medium text-gray-950 dark:text-white">{{ $subject->name }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $summary['present'] }} / {{ $subject->required_sessions }} present
                                    - {{ $summary['completed'] ? 'Present target complete' : $summary['remaining_sessions'].' present to target' }}
                                </p>
                            </div>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                {{ is_null($summary['rate']) ? 'N/A' : number_format($summary['rate'], 1).'%' }}
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600 dark:bg-primary-400" style="width: {{ $progress }}%"></div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
