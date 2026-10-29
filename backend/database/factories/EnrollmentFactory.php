<?php

namespace Database\Factories;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\EnrollmentType;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school_class_id' => SchoolClass::factory(),
            'school_year_id' => fn (array $attributes): int => SchoolClass::query()->findOrFail($attributes['school_class_id'])->school_year_id,
            'type' => EnrollmentType::New,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_on' => now(),
        ];
    }

    public function in(SchoolClass $schoolClass): static
    {
        return $this->state(fn (): array => [
            'school_class_id' => $schoolClass->id,
            'school_year_id' => $schoolClass->school_year_id,
        ]);
    }
}
