<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableSlot>
 */
class TimetableSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'user_id' => fn (array $attributes): int => (int) Subject::query()->findOrFail($attributes['subject_id'])->user_id,
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'label' => fake()->optional()->words(2, true),
            'effective_from' => today()->toDateString(),
        ];
    }
}
