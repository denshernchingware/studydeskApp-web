<?php

namespace App\Actions;

use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTimetableSlot
{
    /**
     * Create a timetable slot in the owner's active semester.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): TimetableSlot
    {
        return $this->save($user, $data);
    }

    /**
     * Update an owner's slot without changing its original effective date or semester.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, TimetableSlot $timetableSlot, array $data): TimetableSlot
    {
        return $this->save($user, $data, $timetableSlot);
    }

    /**
     * Persist a slot while serializing schedule changes for the owner.
     *
     * @param  array<string, mixed>  $data
     */
    private function save(User $user, array $data, ?TimetableSlot $timetableSlot = null): TimetableSlot
    {
        return DB::transaction(function () use ($user, $data, $timetableSlot): TimetableSlot {
            $owner = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $subject = Subject::query()
                ->with('semester')
                ->where('user_id', $owner->getKey())
                ->whereKey($data['subject_id'])
                ->first();

            if (! $subject instanceof Subject || ! $subject->semester instanceof Semester) {
                throw ValidationException::withMessages([
                    'data.subject_id' => 'Choose one of your subjects in a semester.',
                ]);
            }

            $existingSlot = null;

            if ($timetableSlot instanceof TimetableSlot) {
                $existingSlot = TimetableSlot::query()
                    ->with('subject.semester')
                    ->where('user_id', $owner->getKey())
                    ->whereKey($timetableSlot->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $existingSlot instanceof TimetableSlot) {
                    throw (new ModelNotFoundException)->setModel(TimetableSlot::class, [$timetableSlot->getKey()]);
                }

                if ((int) $existingSlot->subject->semester_id !== (int) $subject->semester_id) {
                    throw ValidationException::withMessages([
                        'data.subject_id' => 'A slot cannot be moved to another semester. Create a new slot in that semester instead.',
                    ]);
                }

                $effectiveFrom = $existingSlot->effective_from->toDateString();
            } else {
                if ((int) $owner->active_semester_id !== (int) $subject->semester_id) {
                    throw ValidationException::withMessages([
                        'data.subject_id' => 'Choose a subject in your active semester.',
                    ]);
                }

                $effectiveFrom = CarbonImmutable::parse($data['effective_from'])->toDateString();
            }

            $weekday = (int) $data['weekday'];

            if (! array_key_exists($weekday, TimetableSlot::WEEKDAYS)) {
                throw ValidationException::withMessages([
                    'data.weekday' => 'Choose a valid weekday.',
                ]);
            }

            $startTime = CarbonImmutable::parse($data['start_time'])->format('H:i:s');
            $endTime = CarbonImmutable::parse($data['end_time'])->format('H:i:s');

            if ($endTime <= $startTime) {
                throw ValidationException::withMessages([
                    'data.end_time' => 'The end time must be later than the start time.',
                ]);
            }

            $semester = $subject->semester;
            $effectiveDate = CarbonImmutable::parse($effectiveFrom)->startOfDay();

            if (
                $effectiveDate->lt($semester->starts_on->startOfDay()) ||
                $effectiveDate->gt($semester->ends_on->startOfDay())
            ) {
                throw ValidationException::withMessages([
                    'data.effective_from' => 'The effective date must be within the subject semester.',
                ]);
            }

            $overlappingSlots = TimetableSlot::query()
                ->where('user_id', $owner->getKey())
                ->where('weekday', $weekday)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->whereHas('subject', fn ($query) => $query->where('semester_id', $semester->getKey()));

            if ($existingSlot instanceof TimetableSlot) {
                $overlappingSlots->where('id', '<>', $existingSlot->getKey());
            }

            if ($overlappingSlots->exists()) {
                throw ValidationException::withMessages([
                    'data.start_time' => 'This slot overlaps another slot on the same weekday in this semester.',
                ]);
            }

            $attributes = [
                'user_id' => $owner->getKey(),
                'subject_id' => $subject->getKey(),
                'weekday' => $weekday,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'label' => $data['label'] ?? null,
                'effective_from' => $effectiveFrom,
            ];

            if ($existingSlot instanceof TimetableSlot) {
                $existingSlot->update($attributes);

                return $existingSlot->refresh();
            }

            return TimetableSlot::query()->create($attributes);
        });
    }
}
