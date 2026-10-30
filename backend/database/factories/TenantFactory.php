<?php

namespace Database\Factories;

use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'École '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'country_code' => 'CG',
            'currency' => 'XAF',
            'timezone' => 'Africa/Brazzaville',
            'locale' => 'fr',
            'status' => TenantStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => TenantStatus::Suspended]);
    }
}
