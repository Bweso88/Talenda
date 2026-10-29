<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Policies;

use App\Models\User;

/**
 * Les élèves ne sont jamais supprimés : leur scolarité, leurs paiements et
 * leurs notes restent dans l'historique de l'école.
 */
final class StudentPolicy
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
        return $user->can('student.manage') && $user->can('enrollment.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('student.manage');
    }

    public function delete(): bool
    {
        return false;
    }

    public function issueDocuments(User $user): bool
    {
        return $user->can('document.issue');
    }
}
