<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne « matière » d'un bulletin figé.
 *
 * @property int $id
 * @property int $term_result_id
 * @property int|null $class_subject_id
 * @property int $position
 * @property string $subject_name
 * @property string|null $teacher_name
 * @property string $coefficient
 * @property string|null $average
 * @property int|null $rank
 * @property string|null $class_average
 * @property string|null $class_highest
 * @property string|null $class_lowest
 * @property string|null $comment
 */
#[Fillable(['term_result_id', 'class_subject_id', 'position', 'subject_name', 'teacher_name', 'coefficient', 'average', 'rank', 'class_average', 'class_highest', 'class_lowest', 'comment'])]
class TermSubjectResult extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
            'average' => 'decimal:2',
            'class_average' => 'decimal:2',
            'class_highest' => 'decimal:2',
            'class_lowest' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<TermResult, $this>
     */
    public function termResult(): BelongsTo
    {
        return $this->belongsTo(TermResult::class);
    }
}
