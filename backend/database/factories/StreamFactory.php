<?php

namespace Database\Factories;

use App\Modules\Academic\Models\Stream;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stream>
 */
class StreamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->bothify('S##');

        return [
            'code' => $code,
            'name' => 'Série '.$code,
        ];
    }
}
