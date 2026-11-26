<?php

namespace App\Actions\Telegram;

use App\Actions\AttendanceStatistics;
use App\Ai\Tools\GetAttendanceProgress;
use App\Ai\Tools\GetSemesterSubjects;
use App\Ai\Tools\GetStudySchedule;
use App\Ai\Tools\RecommendSubjectPriorities;
use App\Models\Semester;
use App\Models\User;
use Laravel\Ai\Tools\Request;

class BuildStudyAdvisorContext
{
    /**
     * Build a compact, owner-scoped data snapshot for the advisor's current turn.
     *
     * @return array<string, mixed>
     */
    public function build(User $user, Semester $semester): array
    {
        if ((int) $semester->user_id !== (int) $user->getKey()) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'subjects_and_targets' => $this->decode((new GetSemesterSubjects($user, $semester))->handle(new Request)),
            'attendance_progress' => $this->decode((new GetAttendanceProgress($user, $semester, app(AttendanceStatistics::class)))->handle(new Request)),
            'upcoming_schedule' => $this->decode((new GetStudySchedule($user, $semester))->handle(new Request(['days' => 7]))),
            'deterministic_priorities' => $this->decode((new RecommendSubjectPriorities($user, $semester, app(AttendanceStatistics::class)))->handle(new Request)),
            'syllabus_topics_stored' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : ['message' => $json];
    }
}
