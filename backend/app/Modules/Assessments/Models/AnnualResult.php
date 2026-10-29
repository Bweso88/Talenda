<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Moyenne et rang annuels d'un élève (moyenne des périodes publiées).
 *
 * @property int $id
 * @property int $enrollment_id
 * @property string|null $average
 * @property int|null $rank
 * @property bool $rank_tied
 * @property int $class_size
 */
#[Fillable(['enrollment_id', 'average', 'rank', 'rank_tied', 'class_size'])]
class AnnualResult extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'average' => 'decimal:2',
            'rank_tied' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
