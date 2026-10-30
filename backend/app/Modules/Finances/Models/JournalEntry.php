<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Écriture comptable (SYSCOHADA) produite par un mouvement d'argent :
 * encaissement, dépense, virement, écart de caisse… Toujours équilibrée,
 * jamais modifiée : une erreur se corrige par une écriture inverse.
 *
 * @property int $id
 * @property string $uuid
 * @property Carbon $entry_date
 * @property string $reference
 * @property string $label
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $created_by
 * @property-read Collection<int, JournalLine> $lines
 */
#[Fillable(['entry_date', 'reference', 'label', 'source_type', 'source_id', 'created_by'])]
class JournalEntry extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une écriture comptable ne se modifie pas : passez une écriture inverse.'));
        static::deleting(fn () => throw new LogicException('Une écriture comptable ne se supprime pas : passez une écriture inverse.'));
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
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
