<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use Database\Factories\ClassSubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Matière enseignée dans une classe : coefficient et enseignant.
 *
 * @property int $id
 * @property int $school_class_id
 * @property int $subject_id
 * @property string $coefficient
 * @property int|null $teacher_id
 * @property SchoolClass $schoolClass
 * @property Subject $subject
 * @property StaffMember|null $teacher
 */
#[UseFactory(ClassSubjectFactory::class)]
#[Fillable(['school_class_id', 'subject_id', 'coefficient', 'teacher_id'])]
class ClassSubject extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ClassSubjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // Exact (numeric(4,2)) : jamais de float pour un coefficient.
            'coefficient' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'teacher_id');
    }
}
