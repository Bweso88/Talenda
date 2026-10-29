<?php

declare(strict_types=1);

namespace App\Modules\Finances\Policies;

use App\Models\User;

/**
 * Les factures sont créées automatiquement à l'inscription et ne sont
 * jamais supprimées ; on les complète par des lignes (frais, réductions,
 * corrections).
 */
final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function create(): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function delete(): bool
    {
        return false;
    }
}
