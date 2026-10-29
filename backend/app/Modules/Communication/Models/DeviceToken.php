<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jeton de notification push d'un appareil (indépendant de l'école :
 * un même téléphone reçoit les notifications de toutes ses écoles).
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 * @property Carbon|null $last_seen_at
 */
#[Fillable(['user_id', 'token', 'platform', 'last_seen_at'])]
class DeviceToken extends Model
{
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
