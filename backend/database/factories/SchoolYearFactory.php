<?php

namespace Database\Factories;

use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * À utiliser dans le contexte d'une école active (TenantContext::run).
 *
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2090);

        return [
            'name' => $startYear.'-'.($startYear + 1),
            'starts_on' => Carbon::create($startYear, 10, 1),
            'ends_on' => Carbon::create($startYear + 1, 7, 31),
            'status' => SchoolYearStatus::Draft,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => SchoolYearStatus::Open]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => SchoolYearStatus::Closed]);
    }
}
