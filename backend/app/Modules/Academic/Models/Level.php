<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Contracts\TracksUsage;
use App\Modules\Academic\Policies\StructurePolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Niveau de scolarité (CP1, 6e, Terminale…).
 *
 * @property int $id
 * @property string $uuid
 * @property int $cycle_id
 * @property string $code
 * @property string $name
 * @property int $position
 * @property int|null $next_level_id
 * @property string|null $exam_name
 * @property Cycle $cycle
 * @property Level|null $nextLevel
 */
#[UseFactory(LevelFactory::class)]
#[UsePolicy(StructurePolicy::class)]
#[Fillable(['cycle_id', 'code', 'name', 'position', 'next_level_id', 'exam_name'])]
class Level extends Model implements TracksUsage
{
    use BelongsToTenant;

    /** @use HasFactory<LevelFactory> */
    use HasFactory;

    use HasPublicUuid;

    /**
     * @return BelongsTo<Cycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    /**
     * @return BelongsTo<Level, $this>
     */
    public function nextLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'next_level_id');
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function isExamLevel(): bool
    {
        return $this->exam_name !== null;
    }

    public function isInUse(): bool
    {
        return $this->schoolClasses()->exists();
    }
}
