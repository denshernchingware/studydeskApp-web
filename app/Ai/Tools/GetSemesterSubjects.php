<?php

namespace App\Ai\Tools;

use App\Models\Semester;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSemesterSubjects implements Tool
{
    public function __construct(private readonly User $user, private readonly Semester $semester) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Read the subjects and required-session targets for the linked owner’s active semester.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        if ((int) $this->semester->user_id !== (int) $this->user->getKey()) {
            return 'No active semester data is available.';
        }

        $subjects = $this->user->subjects()
            ->where('semester_id', $this->semester->getKey())
            ->with(['timetableSlots' => fn ($query) => $query
                ->where('user_id', $this->user->getKey())
                ->orderBy('weekday')
                ->orderBy('start_time')])
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn ($subject): array => [
                'name' => $subject->name,
                'code' => $subject->code,
                'required_sessions' => (int) $subject->required_sessions,
                'schedule' => $subject->timetableSlots->map(fn ($slot): array => [
                    'weekday' => $slot->weekday_name,
                    'start' => substr((string) $slot->start_time, 0, 5),
                    'end' => substr((string) $slot->end_time, 0, 5),
                    'effective_from' => $slot->effective_from->toDateString(),
                ])->all(),
            ])->all();

        return json_encode([
            'semester' => $this->semester->name,
            'starts_on' => $this->semester->starts_on->toDateString(),
            'ends_on' => $this->semester->ends_on->toDateString(),
            'subjects' => $subjects,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
