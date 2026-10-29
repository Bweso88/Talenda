<?php

namespace Database\Factories;

use App\Modules\Academic\Models\Cycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cycle>
 */
class CycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CYC-###'),
            'name' => 'Cycle '.fake()->word(),
            'position' => fake()->numberBetween(1, 10),
        ];
    }
}
