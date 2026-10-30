<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Modules\Academic\Models\Term;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Support\AttendanceTotal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Résultat figé d'un élève pour une période publiée : c'est le contenu de
 * son bulletin.
 *
 * @property int $id
 * @property string $uuid
 * @property int $term_id
 * @property int $enrollment_id
 * @property string $class_name
 * @property string|null $average
 * @property int|null $rank
 * @property bool $rank_tied
 * @property int $class_size
 * @property string|null $class_average
 * @property string|null $class_highest
 * @property string|null $class_lowest
 * @property string $total_coefficient
 * @property string|null $total_points
 * @property string|null $distinction
 * @property string|null $council_comment
 * @property string|null $head_teacher_name
 * @property int $absence_minutes
 * @property int $justified_absence_minutes
 * @property int $late_count
 * @property Carbon $published_at
 * @property Term $term
 * @property Enrollment $enrollment
 * @property Collection<int, TermSubjectResult> $subjectResults
 */
#[Fillable(['term_id', 'enrollment_id', 'class_name', 'average', 'rank', 'rank_tied', 'class_size', 'class_average', 'class_highest', 'class_lowest', 'total_coefficient', 'total_points', 'distinction', 'council_comment', 'head_teacher_name', 'absence_minutes', 'justified_absence_minutes', 'late_count', 'published_at'])]
class TermResult extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'average' => 'decimal:2',
            'class_average' => 'decimal:2',
            'class_highest' => 'decimal:2',
            'class_lowest' => 'decimal:2',
            'total_coefficient' => 'decimal:2',
            'total_points' => 'decimal:2',
            'rank_tied' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return HasMany<TermSubjectResult, $this>
     */
    public function subjectResults(): HasMany
    {
        return $this->hasMany(TermSubjectResult::class)->orderBy('position');
    }

    public function rankLabel(): ?string
    {
        if ($this->rank === null) {
            return null;
        }

        return ($this->rank === 1 ? '1er' : $this->rank.'e').($this->rank_tied ? ' ex æquo' : '')." / {$this->class_size}";
    }

    /**
     * « 7 h 30 dont 5 h justifiées », « 3 h, non justifiées », « Aucune ».
     */
    public function absencesLabel(): string
    {
        if ($this->absence_minutes === 0) {
            return 'Aucune';
        }

        return AttendanceTotal::hours($this->absence_minutes)
            .($this->justified_absence_minutes > 0 ? ' dont '.AttendanceTotal::hours($this->justified_absence_minutes).' justifiées' : ', non justifiées');
    }
}
