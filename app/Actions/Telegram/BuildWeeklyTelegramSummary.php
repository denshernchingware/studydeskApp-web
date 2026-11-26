<?php

namespace App\Actions\Telegram;

use App\Actions\AttendanceStatistics;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class BuildWeeklyTelegramSummary
{
    /**
     * Build the weekly report for one owner and semester.
     *
     * @return array{message: string, week_start: string, week_end: string, expected: int, marked: int, present: int, absent: int, unmarked: int, rate: float|null, subjects: array<int, array{name: string, expected: int, marked: int, present: int, absent: int, unmarked: int, target_remaining: int}>}
     */
    public function build(User $user, Semester $semester, CarbonImmutable $weekStart): array
    {
        $timezone = (string) config('app.timezone');
        $weekStart = $weekStart->setTimezone($timezone)->startOfDay();
        $weekEnd = $weekStart->addDays(6);
        $semesterStart = CarbonImmutable::parse($semester->starts_on->toDateString(), $timezone)->startOfDay();
        $semesterEnd = CarbonImmutable::parse($semester->ends_on->toDateString(), $timezone)->startOfDay();
        $studyStart = CarbonImmutable::parse($user->study_start_date->toDateString(), $timezone)->startOfDay();
        $today = CarbonImmutable::today($timezone);
        $rangeStart = $weekStart->max($semesterStart)->max($studyStart);
        $rangeEnd = $weekEnd->min($semesterEnd)->min($today);
        $isWeekInProgress = $weekEnd->gt($today);
        $displayEnd = $isWeekInProgress ? $rangeEnd : $weekEnd;

        $subjects = $user->subjects()
            ->where('semester_id', $semester->getKey())
            ->with(['timetableSlots' => fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->orderBy('weekday')
                ->orderBy('start_time')])
            ->orderBy('name')
            ->get();

        $progressBySubject = collect(app(AttendanceStatistics::class)->forSemester($user, $semester))
            ->keyBy(fn (array $progress): int => (int) $progress['subject']->getKey());
        $weeklyCounts = collect();

        if ($rangeStart->lte($rangeEnd) && $subjects->isNotEmpty()) {
            $weeklyCounts = AttendanceRecord::query()
                ->join('timetable_slots', 'attendance_records.timetable_slot_id', '=', 'timetable_slots.id')
                ->join('subjects', 'timetable_slots.subject_id', '=', 'subjects.id')
                ->where('attendance_records.user_id', $user->getKey())
                ->where('timetable_slots.user_id', $user->getKey())
                ->where('subjects.user_id', $user->getKey())
                ->where('subjects.semester_id', $semester->getKey())
                ->whereColumn('attendance_records.session_date', '>=', 'timetable_slots.effective_from')
                ->whereIn('subjects.id', $subjects->modelKeys())
                ->whereBetween('attendance_records.session_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->select('subjects.id as subject_id', 'attendance_records.status')
                ->selectRaw('COUNT(*) AS marked_count')
                ->groupBy('subjects.id', 'attendance_records.status')
                ->get()
                ->groupBy('subject_id')
                ->map(fn ($counts) => $counts->keyBy(fn (AttendanceRecord $record): string => $record->status instanceof AttendanceStatus
                    ? $record->status->value
                    : (string) $record->status));
        }

        $subjectSummaries = $subjects->map(function (Subject $subject) use ($progressBySubject, $rangeStart, $rangeEnd, $weeklyCounts): array {
            $expected = 0;

            if ($rangeStart->lte($rangeEnd)) {
                foreach ($subject->timetableSlots as $slot) {
                    $effectiveFrom = CarbonImmutable::parse($slot->effective_from->toDateString(), config('app.timezone'))->startOfDay();
                    $expected += $this->countWeekdayOccurrences($rangeStart->max($effectiveFrom), $rangeEnd, (int) $slot->weekday);
                }
            }

            $counts = $weeklyCounts->get($subject->getKey(), collect());
            $present = (int) ($counts->get(AttendanceStatus::Present->value)?->marked_count ?? 0);
            $absent = (int) ($counts->get(AttendanceStatus::Absent->value)?->marked_count ?? 0);
            $progress = $progressBySubject->get($subject->getKey());

            return [
                'name' => $subject->name,
                'expected' => $expected,
                'marked' => $present + $absent,
                'present' => $present,
                'absent' => $absent,
                'unmarked' => max(0, $expected - $present - $absent),
                'target_remaining' => $progress['remaining_sessions'] ?? max(0, (int) $subject->required_sessions),
            ];
        })->all();

        $expected = array_sum(array_column($subjectSummaries, 'expected'));
        $present = array_sum(array_column($subjectSummaries, 'present'));
        $absent = array_sum(array_column($subjectSummaries, 'absent'));
        $marked = $present + $absent;
        $unmarked = max(0, $expected - $marked);
        $rate = $marked === 0 ? null : ($present / $marked) * 100;
        $message = $this->formatMessage($semester, $weekStart, $displayEnd, $isWeekInProgress, $expected, $marked, $present, $absent, $unmarked, $rate, $subjectSummaries);

        return [
            'message' => $message,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'expected' => $expected,
            'marked' => $marked,
            'present' => $present,
            'absent' => $absent,
            'unmarked' => $unmarked,
            'rate' => $rate,
            'subjects' => $subjectSummaries,
        ];
    }

    /**
     * Format a short Telegram message and cap it below Telegram's text limit.
     *
     * @param  array<int, array{name: string, expected: int, marked: int, present: int, absent: int, unmarked: int, target_remaining: int}>  $subjects
     */
    private function formatMessage(
        Semester $semester,
        CarbonImmutable $weekStart,
        CarbonImmutable $displayEnd,
        bool $isWeekInProgress,
        int $expected,
        int $marked,
        int $present,
        int $absent,
        int $unmarked,
        ?float $rate,
        array $subjects,
    ): string {
        if ($expected === 0) {
            return sprintf(
                'No expected study sessions for %s during %s - %s%s.',
                $semester->name,
                $weekStart->format('M j, Y'),
                $displayEnd->format('M j, Y'),
                $isWeekInProgress ? ' (week to date)' : '',
            );
        }

        $message = sprintf(
            "Weekly study summary%s\n%s\n%s - %s\n\nExpected: %d | Marked: %d\nPresent: %d | Absent: %d\nUnmarked: %d | Attendance: %s",
            $isWeekInProgress ? ' (week to date)' : '',
            $semester->name,
            $weekStart->format('M j, Y'),
            $displayEnd->format('M j, Y'),
            $expected,
            $marked,
            $present,
            $absent,
            $unmarked,
            $rate === null ? '-' : number_format($rate, 1).'%',
        );

        $attentionSubjects = array_values(array_filter($subjects, fn (array $subject): bool => $subject['unmarked'] > 0 || $subject['target_remaining'] > 0));

        if ($attentionSubjects === []) {
            return $message."\n\nAll scheduled sessions are marked and subject targets are complete.";
        }

        $message .= "\n\nNeeds attention:";

        foreach (array_slice($attentionSubjects, 0, 8) as $subject) {
            $message .= sprintf("\n- %s: %d unmarked, %d target sessions left", $subject['name'], $subject['unmarked'], $subject['target_remaining']);
        }

        if (count($attentionSubjects) > 8) {
            $message .= sprintf("\n+ %d more subject(s)", count($attentionSubjects) - 8);
        }

        return Str::limit($message, 3800, '...');
    }

    private function countWeekdayOccurrences(CarbonImmutable $startDate, CarbonImmutable $endDate, int $weekday): int
    {
        if ($startDate->gt($endDate)) {
            return 0;
        }

        $days = (int) $startDate->diffInDays($endDate) + 1;
        $occurrences = intdiv($days, 7);

        for ($offset = 0; $offset < $days % 7; $offset++) {
            if ($startDate->addDays($offset)->dayOfWeek === $weekday) {
                $occurrences++;
            }
        }

        return $occurrences;
    }
}
