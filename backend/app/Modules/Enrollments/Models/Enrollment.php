<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Models;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\EnrollmentType;
use App\Modules\Enrollments\Enums\YearEndDecision;
use App\Modules\Enrollments\Policies\EnrollmentPolicy;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inscription d'un élève dans une classe pour une année scolaire.
 *
 * @property int $id
 * @property string $uuid
 * @property int $student_id
 * @property int $school_year_id
 * @property int $school_class_id
 * @property EnrollmentType $type
 * @property EnrollmentStatus $status
 * @property Carbon $enrolled_on
 * @property list<string> $documents
 * @property Carbon|null $left_on
 * @property string|null $leaving_reason
 * @property string|null $destination_school
 * @property YearEndDecision|null $decision
 * @property Student $student
 * @property SchoolYear $schoolYear
 * @property SchoolClass $schoolClass
 */
#[UseFactory(EnrollmentFactory::class)]
#[UsePolicy(EnrollmentPolicy::class)]
#[Fillable(['student_id', 'school_year_id', 'school_class_id', 'type', 'status', 'enrolled_on', 'documents', 'left_on', 'leaving_reason', 'destination_school', 'decision'])]
class Enrollment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'documents' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'type' => EnrollmentType::class,
            'status' => EnrollmentStatus::class,
            'decision' => YearEndDecision::class,
            'enrolled_on' => 'date',
            'left_on' => 'date',
            'documents' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', EnrollmentStatus::active());
    }

    /**
     * Pièces du dossier encore manquantes (libellés).
     *
     * @return list<string>
     */
    public function missingDocuments(): array
    {
        $country = app(TenantContext::class)->get()?->country_code;

        /** @var array<string, string> $required */
        $required = config("talenda.academic_presets.{$country}.enrollment_documents", []);

        return array_values(array_diff_key($required, array_flip($this->documents)));
    }
}
