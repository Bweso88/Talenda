<?php

namespace App\Models;

use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Models\Membership;
use App\Modules\Core\Models\Tenant;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Identité globale d'une personne (docs/PRODUCT_ARCHITECTURE.md §7).
 *
 * N'appartient à aucune école en propre : ses accès passent par ses
 * appartenances (`memberships`), et ses rôles sont attribués école par
 * école (spatie/laravel-permission, team = tenant_id).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property bool $must_change_password
 */
#[Fillable(['name', 'phone', 'email', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPublicUuid;
    use HasRoles;
    use Notifiable;

    /**
     * Valeurs par défaut, alignées sur la migration, pour qu'un modèle
     * tout juste créé les ait sans relecture en base.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'must_change_password' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Écoles auxquelles l'utilisateur a réellement accès (appartenance
     * active, école non suspendue).
     *
     * @return Collection<int, Tenant>
     */
    public function accessibleTenants(): Collection
    {
        return $this->memberships()
            ->usable()
            ->with('tenant')
            ->get()
            ->map(fn (Membership $membership): Tenant => $membership->tenant)
            ->sortBy('name')
            ->values();
    }

    public function membershipFor(Tenant $tenant): ?Membership
    {
        return $this->memberships()
            ->usable()
            ->where('tenant_id', $tenant->getKey())
            ->first();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'school'
            && $this->memberships()->usable()->exists();
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->accessibleTenants();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Tenant && $this->membershipFor($tenant) !== null;
    }
}
