<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Modules\Assessments\Enums\ScoreStatus;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note d'un élève à une évaluation.
 *
 * @property int $id
 * @property int $assessment_id
 * @property int $enrollment_id
 * @property string|null $value
 * @property ScoreStatus $status
 * @property string|null $comment
 * @property Assessment $assessment
 * @property Enrollment $enrollment
 */
#[Fillable(['assessment_id', 'enrollment_id', 'value', 'status', 'comment'])]
class Score extends Model
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'present',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'status' => ScoreStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
