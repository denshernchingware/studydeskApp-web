<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TelegramAccount;
use App\Models\TimetableSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TelegramAdvisorSampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $account = TelegramAccount::query()
            ->with('user.activeSemester')
            ->whereNull('disabled_at')
            ->first();
        $user = $account?->user;
        $semester = $user?->activeSemester;

        if (! $user instanceof User || ! $semester instanceof Semester) {
            $this->command?->warn('No linked Telegram user with an active semester was found. No sample data was added.');

            return;
        }

        $timezone = (string) config('app.timezone');
        $today = CarbonImmutable::today($timezone);
        $semesterStart = CarbonImmutable::parse($semester->starts_on->toDateString(), $timezone);
        $semesterEnd = CarbonImmutable::parse($semester->ends_on->toDateString(), $timezone);
        $trackingStart = CarbonImmutable::parse($user->study_start_date->toDateString(), $timezone);
        $effectiveFrom = $semesterStart->max($trackingStart);

        if ($effectiveFrom->gt($semesterEnd)) {
            $this->command?->warn('The active semester has no dates remaining after the study start date. No sample data was added.');

            return;
        }

        DB::transaction(function () use ($effectiveFrom, $semester, $semesterEnd, $today, $user): void {
            $subjects = [
                'DEMO-MATH' => [
                    'name' => 'Sample: Applied Mathematics',
                    'required_sessions' => 32,
                    'slots' => [
                        ['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '10:00:00'],
                        ['weekday' => 3, 'start_time' => '09:00:00', 'end_time' => '10:00:00'],
                    ],
                ],
                'DEMO-DB' => [
                    'name' => 'Sample: Database Systems',
                    'required_sessions' => 28,
                    'slots' => [
                        ['weekday' => 3, 'start_time' => '11:00:00', 'end_time' => '12:00:00'],
                        ['weekday' => 5, 'start_time' => '11:00:00', 'end_time' => '12:00:00'],
                    ],
                ],
                'DEMO-WRITE' => [
                    'name' => 'Sample: Academic Writing',
                    'required_sessions' => 18,
                    'slots' => [
                        ['weekday' => 2, 'start_time' => '14:00:00', 'end_time' => '15:00:00'],
                        ['weekday' => 4, 'start_time' => '14:00:00', 'end_time' => '15:00:00'],
                    ],
                ],
            ];
            $seededSlots = [];

            foreach ($subjects as $code => $details) {
                $subject = Subject::query()->firstOrCreate(
                    [
                        'user_id' => $user->getKey(),
                        'semester_id' => $semester->getKey(),
                        'code' => $code,
                    ],
                    [
                        'name' => $details['name'],
                        'description' => 'Sample data for testing the StudyDesk Telegram advisor.',
                        'color' => 'blue',
                        'required_sessions' => $details['required_sessions'],
                    ],
                );

                foreach ($details['slots'] as $slotDetails) {
                    $slot = $subject->timetableSlots()->firstOrCreate(
                        [
                            'user_id' => $user->getKey(),
                            'weekday' => $slotDetails['weekday'],
                            'start_time' => $slotDetails['start_time'],
                            'effective_from' => $effectiveFrom->toDateString(),
                        ],
                        [
                            'end_time' => $slotDetails['end_time'],
                            'label' => 'Sample class',
                        ],
                    );

                    $seededSlots[$code][$slotDetails['weekday']] = $slot;
                }
            }

            $attendanceDate = $today->subDay()->min($semesterEnd);

            while ($attendanceDate->gte($effectiveFrom) && $attendanceDate->dayOfWeek !== 3) {
                $attendanceDate = $attendanceDate->subDay();
            }

            if ($attendanceDate->gte($effectiveFrom)) {
                $attendanceExamples = [
                    ['DEMO-MATH', AttendanceStatus::Present],
                    ['DEMO-DB', AttendanceStatus::Absent],
                ];

                foreach ($attendanceExamples as [$code, $status]) {
                    /** @var TimetableSlot|null $slot */
                    $slot = $seededSlots[$code][3] ?? null;

                    if ($slot instanceof TimetableSlot) {
                        AttendanceRecord::query()->firstOrCreate(
                            [
                                'user_id' => $user->getKey(),
                                'timetable_slot_id' => $slot->getKey(),
                                'session_date' => $attendanceDate->toDateString(),
                            ],
                            ['status' => $status],
                        );
                    }
                }
            }
        });

        $this->command?->info(sprintf(
            'Seeded sample subjects and timetable sessions in "%s" (%s to %s).',
            $semester->name,
            $semester->starts_on->toDateString(),
            $semester->ends_on->toDateString(),
        ));
    }
}
