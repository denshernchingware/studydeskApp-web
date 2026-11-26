<?php

namespace App\Ai\Tools;

use App\Models\Semester;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetStudySchedule implements Tool
{
    public function __construct(private readonly User $user, private readonly Semester $semester) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Read scheduled subject sessions in the next 1 to 14 days, bounded by semester dates and timetable effective dates.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate(['days' => ['sometimes', 'integer', 'min:1', 'max:14']]);
        $days = (int) ($arguments['days'] ?? 7);

        if ((int) $this->semester->user_id !== (int) $this->user->getKey()) {
            return 'No active semester schedule is available.';
        }

        $timezone = (string) config('app.timezone');
        $today = CarbonImmutable::today($timezone);
        $now = CarbonImmutable::now($timezone);
        $startDate = $today->max(CarbonImmutable::parse($this->semester->starts_on->toDateString(), $timezone))
            ->max(CarbonImmutable::parse($this->user->study_start_date->toDateString(), $timezone));
        $endDate = $today->addDays($days - 1)
            ->min(CarbonImmutable::parse($this->semester->ends_on->toDateString(), $timezone));

        if ($startDate->gt($endDate)) {
            return 'No scheduled sessions fall within the upcoming date range and active semester.';
        }

        $slots = $this->user->timetableSlots()
            ->with('subject:id,user_id,semester_id,name,code')
            ->whereDate('effective_from', '<=', $endDate->toDateString())
            ->whereHas('subject', fn ($query) => $query
                ->where('user_id', $this->user->getKey())
                ->where('semester_id', $this->semester->getKey()))
            ->orderBy('start_time')
            ->get();

        $sessions = [];

        for ($date = $startDate; $date->lte($endDate); $date = $date->addDay()) {
            foreach ($slots as $slot) {
                if ((int) $slot->weekday !== $date->dayOfWeek
                    || $slot->effective_from->gt($date)
                    || $slot->subject === null) {
                    continue;
                }

                $sessionStart = CarbonImmutable::parse($date->toDateString().' '.$slot->start_time, $timezone);

                if ($sessionStart->lt($now)) {
                    continue;
                }

                $sessions[] = [
                    'date' => $date->toDateString(),
                    'weekday' => $date->format('l'),
                    'start' => substr((string) $slot->start_time, 0, 5),
                    'end' => substr((string) $slot->end_time, 0, 5),
                    'subject' => $slot->subject->name,
                    'code' => $slot->subject->code,
                ];

                if (count($sessions) >= 100) {
                    break 2;
                }
            }
        }

        return json_encode([
            'timezone' => $timezone,
            'sessions' => $sessions,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->min(1)->max(14),
        ];
    }
}
