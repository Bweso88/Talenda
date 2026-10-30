<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Policies\StructurePolicy;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Database\Factories\CycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cycle d'enseignement (préscolaire, primaire, collège, lycée).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $position
 */
#[UseFactory(CycleFactory::class)]
#[UsePolicy(StructurePolicy::class)]
#[Fillable(['code', 'name', 'position'])]
class Cycle extends Model implements TracksUsage
{
    use BelongsToTenant;

    /** @use HasFactory<CycleFactory> */
    use HasFactory;

    /**
     * @return HasMany<Level, $this>
     */
    public function levels(): HasMany
    {
        return $this->hasMany(Level::class)->orderBy('position');
    }

    public function isInUse(): bool
    {
        return $this->levels()->exists();
    }
}
