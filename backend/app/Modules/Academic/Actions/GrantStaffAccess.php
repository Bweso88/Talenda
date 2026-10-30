<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\User;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Enums\MembershipStatus;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\Membership;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Ouvre un accès au back-office à un membre du personnel : compte (réutilisé
 * s'il existe déjà, par exemple chez un parent ou dans une autre école),
 * appartenance à l'école, rôle correspondant à sa fonction.
 */
final class GrantStaffAccess
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RecordAudit $recordAudit,
    ) {}

    /**
     * @return string|null mot de passe provisoire à communiquer, ou null si
     *                     la personne avait déjà un compte (elle garde le sien)
     */
    public function handle(StaffMember $staffMember, string $role, ?Model $actor = null): ?string
    {
        $tenant = $this->tenantContext->get() ?? throw new LogicException('Aucune école active.');

        if ($staffMember->hasAccess()) {
            throw new BusinessRuleViolation("{$staffMember->full_name} a déjà un accès.");
        }

        if ($staffMember->phone === null && $staffMember->email === null) {
            throw new BusinessRuleViolation('Renseignez un téléphone ou un e-mail pour ouvrir un accès.');
        }

        if (! array_key_exists($role, (array) config('talenda.roles')) || $role === 'parent') {
            throw new BusinessRuleViolation('Rôle inconnu.');
        }

        return DB::transaction(function () use ($staffMember, $role, $actor, $tenant): ?string {
            [$user, $temporaryPassword] = $this->findOrCreateUser($staffMember);

            $membership = $this->activeMembership($user, $tenant);

            $user->assignRole($role);
            $staffMember->membership()->associate($membership)->save();

            $this->recordAudit->handle('staff.access_granted', actor: $actor, subject: $staffMember, metadata: [
                'role' => $role,
                'user_uuid' => $user->uuid,
            ]);

            return $temporaryPassword;
        });
    }

    /**
     * @return array{0: User, 1: string|null}
     */
    private function findOrCreateUser(StaffMember $staffMember): array
    {
        $email = $staffMember->email !== null ? mb_strtolower($staffMember->email) : null;

        $user = User::query()
            ->when($staffMember->phone, fn ($query, string $phone) => $query->orWhere('phone', $phone))
            ->when($email, fn ($query, string $email) => $query->orWhere('email', $email))
            ->first();

        if ($user !== null) {
            return [$user, null];
        }

        // Sans symboles : plus facile à dicter au téléphone.
        $temporaryPassword = Str::password(10, symbols: false);

        $user = User::create([
            'name' => $staffMember->first_name.' '.$staffMember->last_name,
            'phone' => $staffMember->phone,
            'email' => $email,
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);

        return [$user, $temporaryPassword];
    }

    private function activeMembership(User $user, Tenant $tenant): Membership
    {
        $membership = Membership::query()->firstOrNew([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $membership->status = MembershipStatus::Active;
        $membership->save();

        return $membership;
    }
}
