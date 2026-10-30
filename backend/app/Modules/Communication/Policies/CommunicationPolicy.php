<?php

declare(strict_types=1);

namespace App\Modules\Communication\Policies;

use App\Models\User;

/**
 * Annonces : direction et secrétariat. Journal SMS et réglages : direction.
 */
final class CommunicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('communication.send') || $user->can('communication.manage');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('communication.send');
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
