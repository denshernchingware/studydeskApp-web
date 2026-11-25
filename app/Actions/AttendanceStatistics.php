<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;

class AttendanceStatistics
{
    /**
     * Summarize expected and marked sessions for the selected semester.
     *
     * @return array<int, array{subject: Subject, expected_sessions: int, present: int, absent: int, marked_sessions: int, remaining_sessions: int, rate: float|null, completed: bool}>
     */
    public function forSemester(User $user, Semester $semester): array
    {
        $subjects = $user->subjects()
            ->where('semester_id', $semester->getKey())
            ->with(['timetableSlots' => fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->orderBy('weekday')
                ->orderBy('start_time')])
            ->orderBy('name')
            ->get();

        if ($subjects->isEmpty()) {
            return [];
        }

        $today = CarbonImmutable::today(config('app.timezone'));
        $semesterStart = CarbonImmutable::parse($semester->starts_on->toDateString(), config('app.timezone'))->startOfDay();
        $semesterEnd = CarbonImmutable::parse($semester->ends_on->toDateString(), config('app.timezone'))->startOfDay();
        $throughDate = $today->min($semesterEnd);
        $studyStartDate = CarbonImmutable::parse($user->study_start_date->toDateString(), config('app.timezone'))->startOfDay();
        $subjectIds = $subjects->modelKeys();

        $markedCounts = AttendanceRecord::query()
            ->join('timetable_slots', 'attendance_records.timetable_slot_id', '=', 'timetable_slots.id')
            ->where('attendance_records.user_id', $user->getKey())
            ->where('timetable_slots.user_id', $user->getKey())
            ->whereIn('timetable_slots.subject_id', $subjectIds)
            ->whereBetween('attendance_records.session_date', [
                $semester->starts_on->toDateString(),
                min($semester->ends_on->toDateString(), $today->toDateString()),
            ])
            ->select('timetable_slots.subject_id', 'attendance_records.status')
            ->selectRaw('COUNT(*) AS marked_count')
            ->groupBy('timetable_slots.subject_id', 'attendance_records.status')
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($counts) => $counts->keyBy(fn (AttendanceRecord $record): string => $record->status instanceof AttendanceStatus
                ? $record->status->value
                : (string) $record->status));

        return $subjects->map(function (Subject $subject) use ($markedCounts, $semesterStart, $semesterEnd, $studyStartDate, $throughDate): array {
            $expectedSessions = $subject->timetableSlots->sum(function ($slot) use ($semesterStart, $semesterEnd, $studyStartDate, $throughDate): int {
                $effectiveFrom = CarbonImmutable::parse($slot->effective_from->toDateString(), config('app.timezone'))->startOfDay();
                $startDate = $semesterStart->max($studyStartDate)->max($effectiveFrom);

                $endDate = $throughDate->min($semesterEnd);

                return $this->countWeekdayOccurrences($startDate, $endDate, (int) $slot->weekday);
            });

            $counts = $markedCounts->get($subject->getKey(), collect());
            $present = (int) $counts->get(AttendanceStatus::Present->value)?->marked_count;
            $absent = (int) $counts->get(AttendanceStatus::Absent->value)?->marked_count;
            $markedSessions = $present + $absent;
            $requiredSessions = (int) $subject->required_sessions;

            return [
                'subject' => $subject,
                'expected_sessions' => $expectedSessions,
                'present' => $present,
                'absent' => $absent,
                'marked_sessions' => $markedSessions,
                'remaining_sessions' => max(0, $requiredSessions - $markedSessions),
                'rate' => $markedSessions === 0 ? null : ($present / $markedSessions) * 100,
                'completed' => $markedSessions >= $requiredSessions,
            ];
        })->all();
    }

    private function countWeekdayOccurrences(CarbonImmutable $startDate, CarbonImmutable $endDate, int $weekday): int
    {
        if ($startDate->gt($endDate)) {
            return 0;
        }

        $days = (int) $startDate->diffInDays($endDate) + 1;
        $occurrences = intdiv($days, 7);
        $remainder = $days % 7;

        for ($offset = 0; $offset < $remainder; $offset++) {
            if ($startDate->addDays($offset)->dayOfWeek === $weekday) {
                $occurrences++;
            }
        }

        return $occurrences;
    }
}
