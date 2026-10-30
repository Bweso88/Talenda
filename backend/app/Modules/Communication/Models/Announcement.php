<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use App\Modules\Communication\Enums\AnnouncementAudience;
use App\Modules\Communication\Policies\CommunicationPolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Annonce de l'école aux familles (toute l'école, un cycle, un niveau ou
 * une classe), publiée immédiatement.
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $body
 * @property AnnouncementAudience $audience
 * @property int|null $audience_id
 * @property string $audience_label
 * @property bool $send_sms
 * @property int $families_count
 * @property int $app_recipients_count
 * @property int $sms_count
 * @property int|null $created_by
 * @property Carbon $published_at
 * @property User|null $creator
 */
#[UsePolicy(CommunicationPolicy::class)]
#[Fillable(['title', 'body', 'audience', 'audience_id', 'audience_label', 'send_sms', 'families_count', 'app_recipients_count', 'sms_count', 'created_by', 'published_at'])]
class Announcement extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'send_sms' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
