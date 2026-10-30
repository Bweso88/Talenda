<?php

namespace Database\Factories;

use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSubject>
 */
class ClassSubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'subject_id' => Subject::factory(),
            'coefficient' => fake()->randomElement(['1.00', '2.00', '3.00', '4.00']),
        ];
    }
}
