<?php

namespace App\Ai\Tools;

use App\Actions\AttendanceStatistics;
use App\Models\Semester;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetAttendanceProgress implements Tool
{
    public function __construct(
        private readonly User $user,
        private readonly Semester $semester,
        private readonly AttendanceStatistics $attendanceStatistics,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Read semester attendance and required-session target progress for the linked owner.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        if ((int) $this->semester->user_id !== (int) $this->user->getKey()) {
            return 'No active semester progress is available.';
        }

        $progress = collect($this->attendanceStatistics->forSemester($this->user, $this->semester))
            ->map(fn (array $summary): array => [
                'subject' => $summary['subject']->name,
                'expected_sessions' => $summary['expected_sessions'],
                'marked_sessions' => $summary['marked_sessions'],
                'present' => $summary['present'],
                'absent' => $summary['absent'],
                'unmarked_expected' => max(0, $summary['expected_sessions'] - $summary['marked_sessions']),
                'attendance_rate_percent' => $summary['rate'] === null ? null : round($summary['rate'], 1),
                'target_required' => (int) $summary['subject']->required_sessions,
                'target_remaining' => $summary['remaining_sessions'],
                'target_complete' => $summary['completed'],
            ])->all();

        return json_encode([
            'semester' => $this->semester->name,
            'timezone' => config('app.timezone'),
            'as_of' => CarbonImmutable::now(config('app.timezone'))->toDateTimeString(),
            'subjects' => $progress,
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
