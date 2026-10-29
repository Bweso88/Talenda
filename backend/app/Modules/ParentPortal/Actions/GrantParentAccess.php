<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Actions;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Enums\MembershipStatus;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\Membership;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Models\Guardian;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Ouvre l'accès à l'app parents à un responsable : compte (réutilisé s'il
 * existe déjà, par exemple dans une autre école ou chez un enseignant),
 * appartenance à l'école avec le rôle « parent », lien explicite entre le
 * compte et la fiche du responsable.
 */
final class GrantParentAccess
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RecordAudit $recordAudit,
    ) {}

    /**
     * @return string|null mot de passe provisoire à communiquer, ou null si
     *                     le parent avait déjà un compte (il garde le sien)
     */
    public function handle(Guardian $guardian, ?Model $actor = null): ?string
    {
        $tenant = $this->tenantContext->get() ?? throw new LogicException('Aucune école active.');

        if ($guardian->user_id !== null) {
            throw new BusinessRuleViolation("{$guardian->full_name} a déjà accès à l'app.");
        }

        if ($guardian->phone === null) {
            throw new BusinessRuleViolation('Renseignez le téléphone du responsable : c\'est son identifiant de connexion.');
        }

        return DB::transaction(function () use ($guardian, $actor, $tenant): ?string {
            $temporaryPassword = null;
            $user = User::query()->where('phone', $guardian->phone)->first();

            if ($user === null) {
                // Sans symboles : plus facile à dicter au téléphone.
                $temporaryPassword = Str::password(10, symbols: false);
                $user = User::create([
                    'name' => trim(($guardian->first_names ?? '').' '.$guardian->last_name),
                    'phone' => $guardian->phone,
                    'email' => $guardian->email !== null && ! User::query()->where('email', mb_strtolower($guardian->email))->exists()
                        ? mb_strtolower($guardian->email)
                        : null,
                    'password' => $temporaryPassword,
                    'must_change_password' => true,
                ]);
            }

            $membership = Membership::query()->firstOrNew(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
            $membership->status = MembershipStatus::Active;
            $membership->save();

            $user->assignRole('parent');
            $guardian->user()->associate($user)->save();

            $this->recordAudit->handle('parent.access_granted', actor: $actor, subject: $guardian, metadata: [
                'user_uuid' => $user->uuid,
                'new_account' => $temporaryPassword !== null,
            ]);

            return $temporaryPassword;
        });
    }

    /**
     * Retire l'accès : le compte reste (il peut servir dans une autre
     * école), seul le lien avec cette fiche est coupé.
     */
    public function revoke(Guardian $guardian, ?Model $actor = null): void
    {
        if ($guardian->user_id === null) {
            return;
        }

        $userUuid = $guardian->user?->uuid;
        $guardian->user()->dissociate()->save();

        $this->recordAudit->handle('parent.access_revoked', actor: $actor, subject: $guardian, metadata: ['user_uuid' => $userUuid]);
    }
}
