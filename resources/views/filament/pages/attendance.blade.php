<x-filament-panels::page>
    <div class="space-y-8">
        @if (! $this->selectedSemester)
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Create a semester to start tracking attendance</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Attendance records belong to a semester so you can review each term separately.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <x-filament::button :href="$this->semesterIndexUrl()" tag="a">View semesters</x-filament::button>
                    <x-filament::button :href="$this->timetableIndexUrl()" color="gray" tag="a">View timetable</x-filament::button>
                </div>
            </section>
        @else
            <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2">
                <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    Semester
                    <select
                        wire:model.live="selectedSemesterId"
                        class="fi-input block w-full rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm outline-none transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:pointer-events-none disabled:opacity-70 dark:border-gray-600 dark:bg-gray-950 dark:text-white"
                    >
                        @foreach ($this->semesters as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->name }} ({{ $semester->starts_on->format('M j, Y') }} – {{ $semester->ends_on->format('M j, Y') }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    Session date
                    <input
                        type="date"
                        wire:model.live="selectedDate"
                        min="{{ $this->selectedSemester->starts_on->toDateString() }}"
                        max="{{ $this->selectedSemester->ends_on->toDateString() }}"
                        class="fi-input block w-full rounded-lg border-gray-300 bg-white text-gray-950 shadow-sm outline-none transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:pointer-events-none disabled:opacity-70 dark:border-gray-600 dark:bg-gray-950 dark:text-white"
                    >
                    @error('selectedDate')
                        <span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>
                    @enderror
                </label>
            </section>

            <section class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Sessions for {{ \Carbon\CarbonImmutable::parse($this->selectedDate, config('app.timezone'))->format('l, F j, Y') }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Mark each scheduled session Present or Absent. Unmarked sessions stay unmarked.</p>
                    </div>
                    @if ($this->selectedDate > today(config('app.timezone'))->toDateString())
                        <span class="rounded-full bg-warning-50 px-3 py-1 text-sm font-medium text-warning-700 dark:bg-warning-400/10 dark:text-warning-300">Future date · marking disabled</span>
                    @endif
                </div>

                @forelse ($this->sessions as $session)
                    @php
                        $slot = $session['slot'];
                        $record = $session['record'];
                        $status = $record?->status?->value;
                        $disabled = ($session['completed'] && $record === null) || $this->selectedDate > today(config('app.timezone'))->toDateString();
                    @endphp

                    <article class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-gray-950 dark:text-white">{{ $slot->subject->name }}</h3>
                                @if ($slot->subject->code)
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $slot->subject->code }}</span>
                                @endif
                                @if ($session['completed'])
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-400/10 dark:text-success-300">Present target reached · existing marks can still be edited</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ \Carbon\CarbonImmutable::parse($slot->start_time)->format('H:i') }}–{{ \Carbon\CarbonImmutable::parse($slot->end_time)->format('H:i') }}
                                @if ($slot->label)
                                    <span>· {{ $slot->label }}</span>
                                @endif
                            </p>
                            @if ($status)
                                <p class="text-sm font-medium {{ $status === 'present' ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">
                                    Marked {{ ucfirst($status) }}
                                </p>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Not marked</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button
                                wire:click="toggleAttendance({{ $slot->id }}, 'present')"
                                :color="$status === 'present' ? 'success' : 'gray'"
                                :disabled="$disabled"
                                wire:loading.attr="disabled"
                                wire:target="toggleAttendance"
                            >{{ $status === 'present' ? 'Clear Present' : 'Present' }}</x-filament::button>
                            <x-filament::button
                                wire:click="toggleAttendance({{ $slot->id }}, 'absent')"
                                :color="$status === 'absent' ? 'danger' : 'gray'"
                                :disabled="$disabled"
                                wire:loading.attr="disabled"
                                wire:target="toggleAttendance"
                            >{{ $status === 'absent' ? 'Clear Absent' : 'Absent' }}</x-filament::button>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-900">
                        <h3 class="font-semibold text-gray-950 dark:text-white">No sessions scheduled</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">There are no effective timetable slots for this weekday and date.</p>
                        <div class="mt-4">
                            <x-filament::button :href="$this->timetableIndexUrl()" tag="a">Review timetable</x-filament::button>
                        </div>
                    </div>
                @endforelse
            </section>

            <section class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Subject summary</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Expected sessions are counted through today inside this semester. Attendance rate uses marked sessions only.</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3">Expected</th>
                                <th class="px-4 py-3">Present</th>
                                <th class="px-4 py-3">Absent</th>
                                <th class="px-4 py-3">Rate</th>
                                <th class="px-4 py-3">Target progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($this->summaries as $summary)
                                @php
                                    $subject = $summary['subject'];
                                    $progress = min(100, (int) round(($summary['present'] / $subject->required_sessions) * 100));
                                @endphp
                                <tr>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-950 dark:text-white">{{ $subject->name }}</th>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $summary['expected_sessions'] }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $summary['present'] }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $summary['absent'] }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ is_null($summary['rate']) ? '—' : number_format($summary['rate'], 1).'%' }}</td>
                                    <td class="min-w-56 px-4 py-3">
                                        <div class="flex items-center justify-between gap-3 text-gray-700 dark:text-gray-200">
                                            <span>{{ $summary['present'] }} / {{ $subject->required_sessions }} present</span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $summary['completed'] ? 'Present target complete' : $summary['remaining_sessions'].' present to target' }}</span>
                                        </div>
                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                            <div class="h-full rounded-full bg-primary-600 dark:bg-primary-400" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No subjects belong to this semester yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Recent attendance</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">The ten most recently marked sessions in this semester.</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Time</th>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($this->recentHistory as $record)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ $record->session_date->format('M j, Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ \Carbon\CarbonImmutable::parse($record->timetableSlot->start_time)->format('H:i') }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $record->timetableSlot->subject->name }}</td>
                                    <td class="px-4 py-3 font-medium {{ $record->status->value === 'present' ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300' }}">{{ ucfirst($record->status->value) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No sessions have been marked in this semester.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
