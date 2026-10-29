<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Enums\StaffPosition;
use App\Modules\Academic\Policies\StaffMemberPolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Models\Membership;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Membre du personnel de l'école (enseignant, secrétariat, caisse…).
 *
 * Existe indépendamment d'un compte de connexion : on peut saisir tout le
 * personnel, puis n'ouvrir un accès qu'à ceux qui en ont besoin.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $membership_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $gender
 * @property StaffPosition $position
 * @property string|null $employee_number
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property-read string $full_name
 * @property Membership|null $membership
 */
#[UseFactory(StaffMemberFactory::class)]
#[UsePolicy(StaffMemberPolicy::class)]
#[Fillable(['first_name', 'last_name', 'gender', 'position', 'employee_number', 'phone', 'email', 'is_active'])]
class StaffMember extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory;

    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'position' => StaffPosition::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Attribute<non-falsy-string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => mb_strtoupper($this->last_name).' '.$this->first_name);
    }

    /**
     * Téléphone toujours enregistré au format E.164 quand il est valide.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $value === null || $value === ''
            ? null
            : (PhoneNumber::normalize($value) ?? $value));
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * Matières enseignées, toutes années confondues.
     *
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'teacher_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function hasAccess(): bool
    {
        return $this->membership_id !== null;
    }
}
