<?php

namespace Database\Factories;

use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_year_id' => SchoolYear::factory(),
            'level_id' => Level::factory(),
            'name' => fake()->unique()->bothify('Classe ??#'),
            'capacity' => 50,
        ];
    }
}
