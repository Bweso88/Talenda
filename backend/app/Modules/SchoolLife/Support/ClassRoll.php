<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Support;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Liste d'appel d'une classe à une date : élèves inscrits ce jour-là
 * (arrivés avant, pas encore partis), par ordre alphabétique.
 */
final class ClassRoll
{
    /**
     * @return Collection<int, Enrollment>
     */
    public static function on(SchoolClass $schoolClass, Carbon $date): Collection
    {
        return $schoolClass->enrollments()
            ->with('student')
            ->whereDate('enrolled_on', '<=', $date)
            ->where(fn (Builder $query) => $query
                ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
                ->orWhere(fn (Builder $left) => $left
                    ->whereIn('status', [EnrollmentStatus::Withdrawn, EnrollmentStatus::TransferredOut])
                    ->whereDate('left_on', '>', $date)))
            ->get()
            ->sortBy(fn (Enrollment $enrollment): string => $enrollment->student->last_name.' '.$enrollment->student->first_names)
            ->values();
    }
}
