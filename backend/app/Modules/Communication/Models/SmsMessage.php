<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Enums\SmsStatus;
use App\Modules\Communication\Policies\CommunicationPolicy;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * SMS envoyé (ou à envoyer) par l'école : journal pour le coût et la
 * preuve d'envoi.
 *
 * @property int $id
 * @property string $phone
 * @property string $body
 * @property int $segments
 * @property SmsPurpose $purpose
 * @property SmsStatus $status
 * @property string $provider
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $created_by
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property User|null $creator
 */
#[UsePolicy(CommunicationPolicy::class)]
#[Fillable(['phone', 'body', 'segments', 'purpose', 'status', 'provider', 'provider_message_id', 'error', 'subject_type', 'subject_id', 'created_by', 'sent_at'])]
class SmsMessage extends Model
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => SmsPurpose::class,
            'status' => SmsStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
