<?php

namespace App\Ai\Tools;

use App\Actions\AttendanceStatistics;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Semester;
use App\Models\TimetableSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RecommendSubjectPriorities implements Tool
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
        return 'Return a deterministic subject-priority ranking using unfinished session targets, recent attendance, and upcoming classes. Include the factors for each ranking.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        if ((int) $this->semester->user_id !== (int) $this->user->getKey()) {
            return 'No active semester data is available for ranking.';
        }

        $timezone = (string) config('app.timezone');
        $today = CarbonImmutable::today($timezone);
        $recentStart = $today->subDays(6);
        $progress = collect($this->attendanceStatistics->forSemester($this->user, $this->semester));

        if ($progress->isEmpty()) {
            return 'There are no subjects in the active semester to rank.';
        }

        $subjectIds = $progress->map(fn (array $row): int => (int) $row['subject']->getKey())->all();
        $semesterStart = CarbonImmutable::parse($this->semester->starts_on->toDateString(), $timezone);
        $studyStart = CarbonImmutable::parse($this->user->study_start_date->toDateString(), $timezone);
        $recentRangeStart = $recentStart->max($semesterStart)->max($studyStart);
        $recentCounts = AttendanceRecord::query()
            ->join('timetable_slots', 'attendance_records.timetable_slot_id', '=', 'timetable_slots.id')
            ->where('attendance_records.user_id', $this->user->getKey())
            ->where('timetable_slots.user_id', $this->user->getKey())
            ->whereIn('timetable_slots.subject_id', $subjectIds)
            ->whereColumn('attendance_records.session_date', '>=', 'timetable_slots.effective_from')
            ->whereBetween('attendance_records.session_date', [$recentRangeStart->toDateString(), $today->toDateString()])
            ->select('timetable_slots.subject_id', 'attendance_records.status')
            ->selectRaw('COUNT(*) AS marked_count')
            ->groupBy('timetable_slots.subject_id', 'attendance_records.status')
            ->get()
            ->groupBy('subject_id');

        $semesterEnd = CarbonImmutable::parse($this->semester->ends_on->toDateString(), $timezone);
        $scheduleStart = $today->max($semesterStart)->max($studyStart);
        $scheduleEnd = $today->addDays(6)->min($semesterEnd);
        $now = CarbonImmutable::now($timezone);
        $upcomingSlots = collect();

        if ($scheduleEnd->gte($today)) {
            $upcomingSlots = $this->user->timetableSlots()
                ->with('subject:id,user_id,semester_id,name')
                ->whereDate('effective_from', '<=', $scheduleEnd->toDateString())
                ->whereHas('subject', fn ($query) => $query
                    ->where('user_id', $this->user->getKey())
                    ->where('semester_id', $this->semester->getKey()))
                ->get();
        }

        $ranked = $progress->map(function (array $row) use ($recentCounts, $today, $upcomingSlots, $timezone, $scheduleStart, $scheduleEnd, $now): array {
            $subject = $row['subject'];
            $counts = $recentCounts->get($subject->getKey(), collect());
            $recentPresent = (int) ($counts->first(fn (AttendanceRecord $record): bool => $record->status === AttendanceStatus::Present)?->marked_count ?? 0);
            $recentAbsent = (int) ($counts->first(fn (AttendanceRecord $record): bool => $record->status === AttendanceStatus::Absent)?->marked_count ?? 0);
            $recentMarked = $recentPresent + $recentAbsent;
            $recentRate = $recentMarked === 0 ? null : ($recentPresent / $recentMarked) * 100;
            $nextSession = null;

            for ($date = $scheduleStart; $date->lte($scheduleEnd); $date = $date->addDay()) {
                $slot = $upcomingSlots->first(fn (TimetableSlot $candidate): bool => (int) $candidate->subject_id === (int) $subject->getKey()
                    && (int) $candidate->weekday === $date->dayOfWeek
                    && $candidate->effective_from->lte($date));

                if ($slot instanceof TimetableSlot) {
                    $candidateSession = CarbonImmutable::parse($date->toDateString().' '.$slot->start_time, $timezone);

                    if ($candidateSession->lt($now)) {
                        continue;
                    }

                    $nextSession = $candidateSession;

                    break;
                }
            }

            $required = max(0, (int) $subject->required_sessions);
            $targetPressure = $required === 0 ? 0.0 : ($row['remaining_sessions'] / $required) * 50;
            $attendancePressure = $recentRate === null ? 15.0 : ((100 - $recentRate) / 100) * 35;
            $sessionSoon = $nextSession !== null && $nextSession->lte($today->addDays(2)->endOfDay());
            $score = $targetPressure + $attendancePressure + ($sessionSoon ? 15 : 0);
            $factors = [];

            if ($row['remaining_sessions'] > 0) {
                $factors[] = sprintf('%d target session(s) remain', $row['remaining_sessions']);
            }

            $factors[] = $recentRate === null
                ? 'no attendance was marked in the last seven days'
                : sprintf('recent attendance is %.0f%% (%d present, %d absent)', $recentRate, $recentPresent, $recentAbsent);

            if ($nextSession !== null) {
                $factors[] = sprintf('next class is %s', $nextSession->setTimezone($timezone)->format('D g:i A'));
            } else {
                $factors[] = 'no class is scheduled in the next seven days';
            }

            return [
                'subject' => $subject->name,
                'priority_score' => round($score, 1),
                'target_remaining' => $row['remaining_sessions'],
                'recent_present' => $recentPresent,
                'recent_absent' => $recentAbsent,
                'recent_attendance_rate_percent' => $recentRate === null ? null : round($recentRate, 1),
                'next_session' => $nextSession?->setTimezone($timezone)->toDateTimeString(),
                'factors' => $factors,
            ];
        })->sortByDesc('priority_score')->take(10)->values()->all();

        return json_encode([
            'method' => 'priority score: unfinished target (0-50), recent attendance gap or no recent marks (0-35), class within two days (+15)',
            'recent_window' => [$recentStart->toDateString(), $today->toDateString()],
            'timezone' => $timezone,
            'priorities' => $ranked,
            'syllabus_topics_stored' => false,
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
