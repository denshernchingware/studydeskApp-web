<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Carbon\CarbonImmutable;

class AttendanceStatistics
{
    public function selectedSemester(User $user, mixed $semesterId): ?Semester
    {
        $selectedSemesterId = filled($semesterId) ? $semesterId : $user->active_semester_id;

        if (! is_numeric($selectedSemesterId)) {
            return null;
        }

        return $user->semesters()->whereKey((int) $selectedSemesterId)->first();
    }

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
                'remaining_sessions' => max(0, $requiredSessions - $present),
                'rate' => $markedSessions === 0 ? null : ($present / $markedSessions) * 100,
                'completed' => $present >= $requiredSessions,
            ];
        })->all();
    }

    /**
     * Summarize semester KPIs from the same subject calculations used by attendance.
     *
     * @return array{present: int, absent: int, active_subjects: int, rate: float|null}
     */
    public function totalsForSemester(User $user, Semester $semester): array
    {
        $summaries = $this->forSemester($user, $semester);
        $present = array_sum(array_column($summaries, 'present'));
        $absent = array_sum(array_column($summaries, 'absent'));
        $marked = $present + $absent;

        return [
            'present' => $present,
            'absent' => $absent,
            'active_subjects' => count($summaries),
            'rate' => $marked === 0 ? null : ($present / $marked) * 100,
        ];
    }

    /**
     * Return today's scheduled slots and their saved attendance states.
     *
     * @return array<int, array{slot: TimetableSlot, record: AttendanceRecord|null}>
     */
    public function todaySchedule(User $user, Semester $semester): array
    {
        $today = CarbonImmutable::today(config('app.timezone'));

        if (
            $today->lt(CarbonImmutable::parse($semester->starts_on->toDateString(), config('app.timezone'))) ||
            $today->gt(CarbonImmutable::parse($semester->ends_on->toDateString(), config('app.timezone'))) ||
            $today->lt(CarbonImmutable::parse($user->study_start_date->toDateString(), config('app.timezone')))
        ) {
            return [];
        }

        $slots = TimetableSlot::query()
            ->with([
                'subject',
                'attendanceRecords' => fn ($query) => $query
                    ->where('user_id', $user->getKey())
                    ->whereDate('session_date', $today->toDateString()),
            ])
            ->where('user_id', $user->getKey())
            ->where('weekday', $today->dayOfWeek)
            ->whereDate('effective_from', '<=', $today->toDateString())
            ->whereHas('subject', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('semester_id', $semester->getKey()))
            ->orderBy('start_time')
            ->get();

        return $slots->map(fn (TimetableSlot $slot): array => [
            'slot' => $slot,
            'record' => $slot->attendanceRecords->first(),
        ])->all();
    }

    /**
     * Return seven calendar-day attendance totals, bounded to the selected semester.
     *
     * @return array{dates: array<int, string>, labels: array<int, string>, present: array<int, int>, absent: array<int, int>}
     */
    public function weeklyConsistency(User $user, Semester $semester): array
    {
        return $this->consistencyForPeriod($user, $semester, 'weekly');
    }

    /**
     * Return daily attendance totals for the current calendar month.
     *
     * @return array{dates: array<int, string>, labels: array<int, string>, present: array<int, int>, absent: array<int, int>}
     */
    public function monthlyConsistency(User $user, Semester $semester): array
    {
        return $this->consistencyForPeriod($user, $semester, 'monthly');
    }

    /**
     * @return array{dates: array<int, string>, labels: array<int, string>, present: array<int, int>, absent: array<int, int>}
     */
    private function consistencyForPeriod(User $user, Semester $semester, string $period): array
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $semesterStart = CarbonImmutable::parse($semester->starts_on->toDateString(), config('app.timezone'))->startOfDay();
        $semesterEnd = CarbonImmutable::parse($semester->ends_on->toDateString(), config('app.timezone'))->startOfDay();
        $studyStart = CarbonImmutable::parse($user->study_start_date->toDateString(), config('app.timezone'))->startOfDay();
        $endDate = $today->min($semesterEnd);
        $windowStart = $period === 'monthly' ? $endDate->startOfMonth() : $endDate->subDays(6);
        $queryStart = $windowStart->max($semesterStart)->max($studyStart);
        $dates = [];
        $labels = [];
        $present = [];
        $absent = [];
        $dayCount = (int) $windowStart->diffInDays($endDate) + 1;
        $labelFormat = $period === 'monthly' ? 'M j' : 'D j';

        for ($offset = 0; $offset < $dayCount; $offset++) {
            $date = $windowStart->addDays($offset);
            $dates[] = $date->toDateString();
            $labels[] = $date->format($labelFormat);
            $present[] = 0;
            $absent[] = 0;
        }

        if ($queryStart->gt($endDate)) {
            return compact('dates', 'labels', 'present', 'absent');
        }

        $counts = AttendanceRecord::query()
            ->join('timetable_slots', 'attendance_records.timetable_slot_id', '=', 'timetable_slots.id')
            ->where('attendance_records.user_id', $user->getKey())
            ->where('timetable_slots.user_id', $user->getKey())
            ->whereBetween('attendance_records.session_date', [$queryStart->toDateString(), $endDate->toDateString()])
            ->whereHas('timetableSlot.subject', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('semester_id', $semester->getKey()))
            ->select('attendance_records.session_date', 'attendance_records.status')
            ->selectRaw('COUNT(*) AS marked_count')
            ->groupBy('attendance_records.session_date', 'attendance_records.status')
            ->get();

        foreach ($counts as $count) {
            $dayIndex = array_search($count->session_date->toDateString(), $dates, true);

            if ($dayIndex === false) {
                continue;
            }

            $status = $count->status instanceof AttendanceStatus ? $count->status->value : (string) $count->status;

            if ($status === AttendanceStatus::Present->value) {
                $present[$dayIndex] = (int) $count->marked_count;
            } elseif ($status === AttendanceStatus::Absent->value) {
                $absent[$dayIndex] = (int) $count->marked_count;
            }
        }

        return compact('dates', 'labels', 'present', 'absent');
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
