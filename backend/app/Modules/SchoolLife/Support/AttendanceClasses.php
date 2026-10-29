<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Support;

use App\Models\User;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Support\TeacherIdentity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Classes dont l'utilisateur peut faire l'appel, dans l'année ouverte :
 * toutes pour la vie scolaire ; pour un enseignant, celles où il enseigne
 * ou dont il est professeur principal.
 */
final class AttendanceClasses
{
    /**
     * @return Builder<SchoolClass>
     */
    public static function for(User $user): Builder
    {
        $query = SchoolClass::query()
            ->whereHas('schoolYear', fn (Builder $year) => $year->where('status', SchoolYearStatus::Open));

        if ($user->can('attendance.manage')) {
            return $query;
        }

        $staffId = TeacherIdentity::staffMemberId($user) ?? 0;

        return $query->where(fn (Builder $mine) => $mine
            ->where('head_teacher_id', $staffId)
            ->orWhereHas('classSubjects', fn (Builder $classSubject) => $classSubject->where('teacher_id', $staffId)));
    }

    /**
     * @return array<int, string>
     */
    public static function options(User $user): array
    {
        return self::for($user)->orderBy('name')->pluck('name', 'id')->all();
    }
}
