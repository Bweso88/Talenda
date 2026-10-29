<?php

namespace Database\Factories;

use App\Modules\Academic\Enums\StaffPosition;
use App\Modules\Academic\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'position' => StaffPosition::Teacher,
            'phone' => '+24206'.fake()->unique()->numerify('#######'),
            'email' => null,
        ];
    }
}
