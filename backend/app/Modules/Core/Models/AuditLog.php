<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Entrée du journal d'audit. Écriture seule : toute tentative de
 * modification ou de suppression lève une exception.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $action
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['tenant_id', 'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'metadata', 'ip_address'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'audit ne se modifie pas.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'audit ne se supprime pas.'));
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
