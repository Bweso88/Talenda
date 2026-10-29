<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Support;

use App\Models\User;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\ClassSubject;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matières que l'utilisateur peut noter : toutes pour la direction des
 * études, seulement les siennes pour un enseignant. Années non clôturées.
 */
final class GradableClassSubjects
{
    /**
     * @return Builder<ClassSubject>
     */
    public static function for(User $user): Builder
    {
        return ClassSubject::query()
            ->whereHas('schoolClass.schoolYear', fn (Builder $query) => $query->where('status', '!=', SchoolYearStatus::Closed))
            ->when(! $user->can('assessment.manage'), fn (Builder $query) => $query->where('teacher_id', TeacherIdentity::staffMemberId($user) ?? 0));
    }

    /**
     * Options « 6e A — Mathématiques » pour les listes déroulantes.
     *
     * @return array<int, string>
     */
    public static function options(User $user): array
    {
        return self::for($user)
            ->with(['schoolClass', 'subject'])
            ->get()
            ->sortBy(fn (ClassSubject $classSubject): string => $classSubject->schoolClass->name.' '.$classSubject->subject->name)
            ->mapWithKeys(fn (ClassSubject $classSubject): array => [$classSubject->id => "{$classSubject->schoolClass->name} — {$classSubject->subject->name}"])
            ->all();
    }
}
