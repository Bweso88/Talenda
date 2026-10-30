<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Policies\SchoolYearPolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property SchoolYearStatus $status
 */
#[UseFactory(SchoolYearFactory::class)]
#[UsePolicy(SchoolYearPolicy::class)]
#[Fillable(['name', 'starts_on', 'ends_on', 'status'])]
class SchoolYear extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SchoolYearFactory> */
    use HasFactory;

    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => SchoolYearStatus::class,
        ];
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('position');
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', SchoolYearStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === SchoolYearStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === SchoolYearStatus::Closed;
    }
}
