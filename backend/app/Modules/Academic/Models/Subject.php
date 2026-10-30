<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Policies\StructurePolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Matière enseignée dans l'école.
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name
 */
#[UseFactory(SubjectFactory::class)]
#[UsePolicy(StructurePolicy::class)]
#[Fillable(['code', 'name'])]
class Subject extends Model implements TracksUsage
{
    use BelongsToTenant;

    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    use HasPublicUuid;

    /**
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function isInUse(): bool
    {
        return $this->classSubjects()->exists();
    }
}
