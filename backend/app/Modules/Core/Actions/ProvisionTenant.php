<?php

declare(strict_types=1);

namespace App\Modules\Core\Actions;

use App\Models\User;
use App\Modules\Core\Enums\MembershipStatus;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Membership;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Support\CountryPresets;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Crée une école prête à l'emploi (docs/PRODUCT_ARCHITECTURE.md §7) :
 * l'école avec les valeurs de son gabarit de pays, son premier
 * administrateur (compte existant réutilisé s'il a déjà le même e-mail ou
 * téléphone), et l'entrée d'audit correspondante.
 *
 * Ajouter une école ne demande ni code ni déploiement : uniquement cette
 * action, appelée depuis le back-office plateforme ou un seeder.
 */
final class ProvisionTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RecordAudit $recordAudit,
    ) {}

    /**
     * @param  array{name: string, slug: string, country_code?: string|null}  $school
     * @param  array{name: string, email?: string|null, phone?: string|null, password: string}  $admin
     */
    public function handle(array $school, array $admin, ?Model $actor = null): Tenant
    {
        $countryCode = $school['country_code'] ?? CountryPresets::defaultCode();
        $country = CountryPresets::get($countryCode);

        if ($country === null) {
            throw new InvalidArgumentException("Aucun gabarit de pays pour « {$countryCode} ».");
        }

        return DB::transaction(function () use ($school, $admin, $actor, $countryCode, $country): Tenant {
            $tenant = Tenant::create([
                'name' => $school['name'],
                'slug' => $school['slug'],
                'country_code' => $countryCode,
                'currency' => $country['currency'],
                'timezone' => $country['timezone'],
                'locale' => $country['locale'],
                'status' => TenantStatus::Active,
            ]);

            $user = $this->findOrCreateUser($admin);

            Membership::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'status' => MembershipStatus::Active,
            ]);

            $this->tenantContext->run($tenant, function () use ($user): void {
                $user->assignRole('school_admin');
            });

            $this->recordAudit->handle(
                action: 'tenant.provisioned',
                actor: $actor,
                subject: $tenant,
                metadata: ['admin_user_uuid' => $user->uuid],
                tenantId: $tenant->id,
            );

            return $tenant;
        });
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, password: string}  $admin
     */
    private function findOrCreateUser(array $admin): User
    {
        $email = isset($admin['email']) ? mb_strtolower($admin['email']) : null;
        $phone = $admin['phone'] ?? null;

        if ($email === null && $phone === null) {
            throw new InvalidArgumentException('L\'administrateur doit avoir un e-mail ou un téléphone.');
        }

        $existing = User::query()
            ->when($email, fn ($query) => $query->orWhere('email', $email))
            ->when($phone, fn ($query) => $query->orWhere('phone', $phone))
            ->first();

        return $existing ?? User::create([
            'name' => $admin['name'],
            'email' => $email,
            'phone' => $phone,
            'password' => $admin['password'],
            'must_change_password' => true,
        ]);
    }
}
