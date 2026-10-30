<?php

namespace Database\Factories;

use App\Modules\Academic\Models\Cycle;
use App\Modules\Academic\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cycle_id' => Cycle::factory(),
            'code' => fake()->unique()->bothify('LVL-###'),
            'name' => 'Niveau '.fake()->word(),
            'position' => fake()->numberBetween(1, 20),
        ];
    }
}
