<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Enums\GuardianRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Lien élève ↔ responsable. Toujours créé via ce modèle (voir la note de
 * BelongsToTenant sur attach()).
 *
 * @property int $id
 * @property int $student_id
 * @property int $guardian_id
 * @property GuardianRelationship $relationship
 * @property bool $is_primary_contact
 * @property bool $is_financially_responsible
 * @property Student $student
 * @property Guardian $guardian
 */
#[Fillable(['student_id', 'guardian_id', 'relationship', 'is_primary_contact', 'is_financially_responsible'])]
class StudentGuardian extends Pivot
{
    use BelongsToTenant;

    protected $table = 'student_guardians';

    public $incrementing = true;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_primary_contact' => false,
        'is_financially_responsible' => false,
    ];

    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
            'is_primary_contact' => 'boolean',
            'is_financially_responsible' => 'boolean',
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
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
