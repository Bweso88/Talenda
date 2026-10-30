<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Models\User;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Policies\AssessmentPolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Évaluation (devoir, interrogation, composition) d'une matière dans une
 * classe, pour une période.
 *
 * @property int $id
 * @property string $uuid
 * @property int $class_subject_id
 * @property int $term_id
 * @property int $assessment_type_id
 * @property string $title
 * @property Carbon $held_on
 * @property string $max_score
 * @property int|null $created_by
 * @property ClassSubject $classSubject
 * @property Term $term
 * @property AssessmentType $assessmentType
 */
#[UsePolicy(AssessmentPolicy::class)]
#[Fillable(['class_subject_id', 'term_id', 'assessment_type_id', 'title', 'held_on', 'max_score', 'created_by'])]
class Assessment extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'max_score' => 20,
    ];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'max_score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ClassSubject, $this>
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<AssessmentType, $this>
     */
    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Score, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
