<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'timetable_slot_id' => TimetableSlot::factory(),
            'user_id' => fn (array $attributes): int => (int) TimetableSlot::query()
                ->findOrFail($attributes['timetable_slot_id'])
                ->user_id,
            'session_date' => today()->toDateString(),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
        ];
    }
}
