<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Policies;

use App\Models\User;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;

final class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('student.view');
    }

    public function view(User $user): bool
    {
        return $user->can('student.view');
    }

    public function create(User $user): bool
    {
        return $user->can('enrollment.manage');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.manage') && ! $enrollment->schoolYear->isClosed();
    }

    /**
     * Seule une préinscription (erreur de saisie, place libérée) peut être
     * supprimée ; un départ se déclare, il ne s'efface pas.
     */
    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->update($user, $enrollment) && $enrollment->status === EnrollmentStatus::PreRegistered;
    }
}
