<?php

namespace Database\Factories;

use App\Modules\Enrollments\Enums\Gender;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'registration_number' => fake()->unique()->numerify('99-####'),
            'last_name' => fake()->lastName(),
            'first_names' => fake()->firstName($gender === Gender::Female ? 'female' : 'male'),
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-18 years', '-6 years'),
            'birth_place' => fake()->randomElement(['Brazzaville', 'Pointe-Noire', 'Dolisie', 'Nkayi', 'Owando']),
            'nationality' => 'CG',
        ];
    }
}
