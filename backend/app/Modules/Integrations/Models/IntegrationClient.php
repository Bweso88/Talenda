<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Models\User;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Application partenaire (Scolia…) autorisée à lire les données de l'école
 * avec une clé secrète.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $name
 * @property string $token_hash
 * @property string $token_prefix
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property User|null $creator
 */
#[Fillable(['name', 'token_hash', 'token_prefix', 'last_used_at', 'revoked_at', 'created_by'])]
class IntegrationClient extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
