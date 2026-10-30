<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Models\User;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Enums\MembershipStatus;
use App\Modules\Core\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Appartenance d'un utilisateur à une école.
 *
 * Volontairement pas filtrée par TenantScope : c'est par ses appartenances
 * qu'on sait à quelles écoles un utilisateur a accès, avant qu'une école
 * soit active. On y accède donc toujours depuis un utilisateur
 * ($user->memberships) ou une école ($tenant->memberships), jamais par une
 * requête globale.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $user_id
 * @property MembershipStatus $status
 * @property Tenant $tenant
 * @property User $user
 */
#[Fillable(['tenant_id', 'user_id', 'status', 'last_seen_at'])]
class Membership extends Model
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Appartenances actives dans une école elle-même accessible.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active)
            ->whereHas('tenant', fn (Builder $tenant) => $tenant->where('status', '!=', TenantStatus::Suspended));
    }
}
