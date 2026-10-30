<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\SchoolClass;

/**
 * Reprend les matières et coefficients d'une classe modèle (ex. « 6e A »
 * vers « 6e B ») sans toucher aux matières déjà présentes. Les enseignants
 * ne sont pas recopiés : ils diffèrent d'une classe à l'autre.
 */
final class CopyClassSubjects
{
    /**
     * @return int nombre de matières ajoutées
     */
    public function handle(SchoolClass $from, SchoolClass $to): int
    {
        $existingSubjectIds = $to->classSubjects()->pluck('subject_id');

        $missing = $from->classSubjects()
            ->whereNotIn('subject_id', $existingSubjectIds)
            ->get();

        $missing->each(fn (ClassSubject $classSubject) => ClassSubject::create([
            'school_class_id' => $to->id,
            'subject_id' => $classSubject->subject_id,
            'coefficient' => $classSubject->coefficient,
        ]));

        return $missing->count();
    }
}
