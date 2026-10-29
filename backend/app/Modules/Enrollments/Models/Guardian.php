<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Models;

use App\Models\User;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Responsable d'un ou plusieurs élèves (parent, tuteur). Identifié dans
 * l'école par son téléphone : un parent de trois enfants n'est saisi
 * qu'une fois.
 *
 * @property int $id
 * @property string $uuid
 * @property string $last_name
 * @property string|null $first_names
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $profession
 * @property string|null $address
 * @property int|null $user_id compte de l'app parents, relié par l'école
 * @property User|null $user
 * @property-read string $full_name
 */
#[UseFactory(GuardianFactory::class)]
#[Fillable(['last_name', 'first_names', 'phone', 'email', 'profession', 'address'])]
class Guardian extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    use HasPublicUuid;

    /**
     * @return Attribute<string, string>
     */
    protected function lastName(): Attribute
    {
        return Attribute::set(fn (string $value): string => mb_strtoupper(trim($value)));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => blank($value)
            ? null
            : (PhoneNumber::normalize($value) ?? $value));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->last_name.' '.$this->first_names));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Student, $this, StudentGuardian>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->using(StudentGuardian::class)
            ->withPivot(['id', 'relationship', 'is_primary_contact', 'is_financially_responsible'])
            ->withTimestamps();
    }
}
