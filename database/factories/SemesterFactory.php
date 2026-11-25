<?php

namespace Database\Factories;

use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'starts_on' => today()->subMonths(4)->toDateString(),
            'ends_on' => today()->addMonths(2)->toDateString(),
        ];
    }
}
