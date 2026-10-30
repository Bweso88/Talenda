<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Modules\Assessments\Enums\AbsencePolicy;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Règles de notation de l'école.
 *
 * @property int $id
 * @property int $max_score
 * @property string $passing_average
 * @property AbsencePolicy $absence_policy
 * @property list<array{name: string, min: float|int}> $distinctions
 */
#[Fillable(['max_score', 'passing_average', 'absence_policy', 'distinctions'])]
class GradingSetting extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'passing_average' => 'decimal:2',
            'absence_policy' => AbsencePolicy::class,
            'distinctions' => 'array',
        ];
    }

    /**
     * Réglages de l'école active (créés avec l'école, voir SetUpGrading).
     */
    public static function current(): self
    {
        return self::query()->firstOrFail();
    }

    /**
     * Mention correspondant à une moyenne (seuils du plus haut au plus bas).
     */
    public function distinctionFor(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        foreach ($this->distinctions as $distinction) {
            if ($average >= (float) $distinction['min']) {
                return $distinction['name'];
            }
        }

        return null;
    }
}
