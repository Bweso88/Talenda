<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Modules\Assessments\Policies\GradingSettingsPolicy;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type d'évaluation (devoir, composition…) et son poids dans la moyenne
 * d'une matière.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $weight
 * @property int $position
 */
#[UsePolicy(GradingSettingsPolicy::class)]
#[Fillable(['code', 'name', 'weight', 'position'])]
class AssessmentType extends Model implements TracksUsage
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function isInUse(): bool
    {
        return $this->assessments()->exists();
    }
}
