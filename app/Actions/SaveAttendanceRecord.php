<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class SaveAttendanceRecord
{
    /**
     * Toggle a status for a valid scheduled session.
     */
    public function toggle(User $user, int $slotId, int $semesterId, string $sessionDate, AttendanceStatus $status): ?AttendanceRecord
    {
        return $this->save($user, $slotId, $semesterId, $sessionDate, $status, toggle: true);
    }

    /**
     * Set a status idempotently for a valid scheduled session.
     */
    public function set(User $user, int $slotId, int $semesterId, string $sessionDate, AttendanceStatus $status): AttendanceRecord
    {
        $record = $this->save($user, $slotId, $semesterId, $sessionDate, $status, toggle: false);

        if (! $record instanceof AttendanceRecord) {
            throw new LogicException('Setting attendance must return a saved record.');
        }

        return $record;
    }

    private function save(
        User $user,
        int $slotId,
        int $semesterId,
        string $sessionDate,
        AttendanceStatus $status,
        bool $toggle,
    ): ?AttendanceRecord {
        Validator::make(
            ['session_date' => $sessionDate],
            ['session_date' => ['required', 'date_format:Y-m-d']],
        )->validate();

        $date = CarbonImmutable::parse($sessionDate, config('app.timezone'))->startOfDay();

        return DB::transaction(function () use ($user, $slotId, $semesterId, $sessionDate, $date, $status, $toggle): ?AttendanceRecord {
            $owner = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $semester = Semester::query()
                ->where('user_id', $owner->getKey())
                ->whereKey($semesterId)
                ->lockForUpdate()
                ->first();

            if (! $semester instanceof Semester) {
                throw ValidationException::withMessages([
                    'selectedSemesterId' => 'Choose one of your semesters.',
                ]);
            }

            $slot = TimetableSlot::query()
                ->where('user_id', $owner->getKey())
                ->whereKey($slotId)
                ->lockForUpdate()
                ->first();

            if (! $slot instanceof TimetableSlot) {
                throw (new ModelNotFoundException)->setModel(TimetableSlot::class, [$slotId]);
            }

            $subject = Subject::query()
                ->where('user_id', $owner->getKey())
                ->whereKey($slot->subject_id)
                ->lockForUpdate()
                ->first();

            if (! $subject instanceof Subject || (int) $subject->semester_id !== (int) $semester->getKey()) {
                throw ValidationException::withMessages([
                    'selectedSemesterId' => 'This slot does not belong to the selected semester.',
                ]);
            }

            if ($date->lt($semester->starts_on->startOfDay()) || $date->gt($semester->ends_on->startOfDay())) {
                throw ValidationException::withMessages([
                    'selectedDate' => 'Choose a date inside the selected semester.',
                ]);
            }

            if ($date->gt(CarbonImmutable::today(config('app.timezone')))) {
                throw ValidationException::withMessages([
                    'selectedDate' => 'Attendance cannot be marked for a future date.',
                ]);
            }

            if ($date->lt($owner->study_start_date->startOfDay())) {
                throw ValidationException::withMessages([
                    'selectedDate' => 'Attendance cannot be marked before your study start date.',
                ]);
            }

            if ($date->lt($slot->effective_from->startOfDay()) || (int) $slot->weekday !== $date->dayOfWeek) {
                throw ValidationException::withMessages([
                    'selectedDate' => 'This slot is not scheduled on the selected date.',
                ]);
            }

            $now = CarbonImmutable::now(config('app.timezone'));
            $scheduledStart = CarbonImmutable::parse(
                $date->toDateString().' '.$slot->start_time,
                config('app.timezone'),
            );

            if ($date->isSameDay($now) && $now->lt($scheduledStart)) {
                throw ValidationException::withMessages([
                    'selectedDate' => 'Attendance opens at the scheduled start time.',
                ]);
            }

            $record = AttendanceRecord::query()
                ->where('user_id', $owner->getKey())
                ->where('timetable_slot_id', $slot->getKey())
                ->whereDate('session_date', $sessionDate)
                ->lockForUpdate()
                ->first();

            if (! $toggle && $record instanceof AttendanceRecord && $record->status === $status) {
                return $record;
            }

            $presentCount = AttendanceRecord::query()
                ->where('user_id', $owner->getKey())
                ->where('status', AttendanceStatus::Present->value)
                ->whereBetween('session_date', [
                    $semester->starts_on->toDateString(),
                    $semester->ends_on->toDateString(),
                ])
                ->whereHas('timetableSlot', fn ($query) => $query
                    ->where('user_id', $owner->getKey())
                    ->where('subject_id', $subject->getKey()))
                ->count();

            if (! $record instanceof AttendanceRecord && $presentCount >= $subject->required_sessions) {
                throw ValidationException::withMessages([
                    'selectedDate' => 'This subject has reached its required number of present sessions.',
                ]);
            }

            if ($record instanceof AttendanceRecord) {
                if ($toggle && $record->status === $status) {
                    $record->delete();

                    return null;
                }

                $record->update(['status' => $status]);

                return $record->refresh();
            }

            return AttendanceRecord::query()->create([
                'user_id' => $owner->getKey(),
                'timetable_slot_id' => $slot->getKey(),
                'session_date' => $sessionDate,
                'status' => $status,
            ]);
        }, attempts: 3);
    }
}
