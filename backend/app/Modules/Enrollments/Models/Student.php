<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Models;

use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\Gender;
use App\Modules\Enrollments\Policies\StudentPolicy;
use App\Modules\Finances\Models\Invoice;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Élève : identité permanente, conservée d'une année à l'autre. Sa
 * scolarité (classe, statut) est portée par ses inscriptions.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $registration_number
 * @property string $last_name
 * @property string $first_names
 * @property Gender $gender
 * @property Carbon $birth_date
 * @property string|null $birth_place
 * @property string|null $nationality
 * @property string|null $address
 * @property string|null $previous_school
 * @property string|null $medical_notes
 * @property string|null $photo_path
 * @property-read string $full_name
 * @property-read Enrollment|null $activeEnrollment
 */
#[UseFactory(StudentFactory::class)]
#[UsePolicy(StudentPolicy::class)]
#[Fillable(['registration_number', 'last_name', 'first_names', 'gender', 'birth_date', 'birth_place', 'nationality', 'address', 'previous_school', 'medical_notes', 'photo_path'])]
class Student extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
        ];
    }

    /**
     * Nom en majuscules, comme sur les documents officiels.
     *
     * @return Attribute<string, string>
     */
    protected function lastName(): Attribute
    {
        return Attribute::set(fn (string $value): string => mb_strtoupper(trim($value)));
    }

    /**
     * @return Attribute<non-falsy-string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => $this->last_name.' '.$this->first_names);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Inscription en cours (préinscription ou inscription), la plus récente.
     *
     * @return HasOne<Enrollment, $this>
     */
    public function activeEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->ofMany(
            ['enrolled_on' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereIn('status', EnrollmentStatus::active()),
        );
    }

    /**
     * @return BelongsToMany<Guardian, $this, StudentGuardian>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->using(StudentGuardian::class)
            ->withPivot(['id', 'relationship', 'is_primary_contact', 'is_financially_responsible'])
            ->withTimestamps();
    }

    /**
     * Factures de scolarité (module Finances), une par inscription.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<StudentGuardian, $this>
     */
    public function guardianLinks(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }
}
