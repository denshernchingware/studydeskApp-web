<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
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
            'code' => fake()->optional()->bothify('??##'),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->randomElement(['violet', 'blue', 'green', 'orange', 'pink', 'teal']),
            'required_sessions' => fake()->numberBetween(1, 40),
        ];
    }
}
