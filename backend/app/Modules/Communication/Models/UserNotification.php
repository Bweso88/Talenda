<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use App\Modules\Communication\Enums\NotificationType;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notification affichée dans l'app (bulletin publié, absence, paiement,
 * annonce…), pour un compte dans une école.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property NotificationType $type
 * @property string $title
 * @property string $body
 * @property array<string, mixed> $data
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property User $user
 */
#[Fillable(['user_id', 'type', 'title', 'body', 'data', 'read_at'])]
class UserNotification extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'data' => 'array',
            'read_at' => 'datetime',
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
