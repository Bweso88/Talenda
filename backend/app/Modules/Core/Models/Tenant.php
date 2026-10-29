<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Models\User;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une école cliente. Modèle de plateforme : il n'est pas filtré par
 * TenantScope (c'est lui qui définit le tenant).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $country_code
 * @property string $currency
 * @property string $timezone
 * @property string $locale
 * @property TenantStatus $status
 */
#[UseFactory(TenantFactory::class)]
#[Fillable(['name', 'slug', 'country_code', 'currency', 'timezone', 'locale', 'status'])]
class Tenant extends Model implements HasName
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasPublicUuid;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * @return HasMany<TenantModule, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    public function hasModule(string $module): bool
    {
        return $this->modules
            ->contains(fn (TenantModule $tenantModule): bool => $tenantModule->module === $module && $tenantModule->enabled);
    }
}
