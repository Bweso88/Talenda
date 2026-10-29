<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Policies;

use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Support\TeacherIdentity;
use App\Modules\Assessments\Models\Assessment;

/**
 * Un enseignant ne voit et ne note que ses propres matières ; la direction
 * des études voit tout. Les notes ne se modifient que tant que la période
 * est ouverte à la saisie.
 */
final class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assessment.enter');
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->canGrade($user, $assessment->classSubject);
    }

    public function create(User $user): bool
    {
        return $user->can('assessment.enter');
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $this->canGrade($user, $assessment->classSubject)
            && $assessment->term->status === TermStatus::Open;
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $this->update($user, $assessment);
    }

    public function canGrade(User $user, ClassSubject $classSubject): bool
    {
        if ($user->can('assessment.manage')) {
            return true;
        }

        return $user->can('assessment.enter')
            && $classSubject->teacher_id !== null
            && $classSubject->teacher_id === TeacherIdentity::staffMemberId($user);
    }
}
