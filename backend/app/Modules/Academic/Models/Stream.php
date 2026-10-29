<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Policies\StructurePolicy;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Database\Factories\StreamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Série ou filière du lycée (A, C, D…).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
#[UseFactory(StreamFactory::class)]
#[UsePolicy(StructurePolicy::class)]
#[Fillable(['code', 'name'])]
class Stream extends Model implements TracksUsage
{
    use BelongsToTenant;

    /** @use HasFactory<StreamFactory> */
    use HasFactory;

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function isInUse(): bool
    {
        return $this->schoolClasses()->exists();
    }
}
