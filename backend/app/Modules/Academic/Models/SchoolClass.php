<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Policies\SchoolClassPolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Models\Enrollment;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Classe d'une année scolaire (« 6e B », « Tle C1 »…).
 *
 * @property int $id
 * @property string $uuid
 * @property int $school_year_id
 * @property int|null $site_id
 * @property int $level_id
 * @property int|null $stream_id
 * @property string $name
 * @property int|null $capacity
 * @property string|null $room
 * @property int|null $head_teacher_id
 * @property SchoolYear $schoolYear
 * @property Site|null $site
 * @property Level $level
 * @property Stream|null $stream
 * @property StaffMember|null $headTeacher
 */
#[UseFactory(SchoolClassFactory::class)]
#[UsePolicy(SchoolClassPolicy::class)]
#[Fillable(['school_year_id', 'site_id', 'level_id', 'stream_id', 'name', 'capacity', 'room', 'head_teacher_id'])]
class SchoolClass extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    use HasPublicUuid;

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * Une classe sans site précisé va sur le site principal : dans une
     * école à un seul site, personne n'a à le choisir.
     */
    protected static function booted(): void
    {
        static::creating(function (SchoolClass $schoolClass): void {
            $schoolClass->site_id ??= Site::main()?->id;
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function headTeacher(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'head_teacher_id');
    }

    /**
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Élèves qui occupent une place (préinscrits et inscrits).
     */
    public function occupiedSeats(): int
    {
        return $this->enrollments()->active()->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->occupiedSeats() >= $this->capacity;
    }

    /**
     * Somme des coefficients : dénominateur de la moyenne générale.
     */
    public function totalCoefficient(): float
    {
        return (float) $this->classSubjects()->sum('coefficient');
    }
}
