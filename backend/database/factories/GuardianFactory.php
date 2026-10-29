<?php

namespace Database\Factories;

use App\Modules\Enrollments\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'last_name' => fake()->lastName(),
            'first_names' => fake()->firstName(),
            'phone' => '+24205'.fake()->unique()->numerify('#######'),
            'profession' => fake()->jobTitle(),
        ];
    }
}
