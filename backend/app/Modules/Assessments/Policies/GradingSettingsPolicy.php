<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Policies;

use App\Models\User;
use App\Modules\Core\Contracts\TracksUsage;

/**
 * Règles de notation (types d'évaluation, barème, mentions) : réservées à
 * la direction des études.
 */
final class GradingSettingsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('term.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('term.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('term.manage');
    }

    public function delete(User $user, TracksUsage $model): bool
    {
        return $user->can('term.manage') && ! $model->isInUse();
    }
}
