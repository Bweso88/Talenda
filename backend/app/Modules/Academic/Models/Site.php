<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Policies\StructurePolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Models\CashRegister;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site (implantation) d'une école : ses classes, donc ses élèves, et ses
 * caisses y sont rattachées. Une école à un seul site n'a rien à régler.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $address
 * @property bool $is_active
 */
#[UsePolicy(StructurePolicy::class)]
#[Fillable(['name', 'address', 'is_active'])]
class Site extends Model implements TracksUsage
{
    use BelongsToTenant;
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * @return HasMany<CashRegister, $this>
     */
    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    public function isInUse(): bool
    {
        return $this->schoolClasses()->exists() || $this->cashRegisters()->exists();
    }

    /**
     * @param  Builder<Site>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * L'école a plusieurs sites actifs : les écrans montrent alors le site.
     */
    public static function isMultiSite(): bool
    {
        return self::query()->active()->count() > 1;
    }

    /**
     * Site proposé par défaut (le seul, ou le premier créé).
     */
    public static function main(): ?self
    {
        return self::query()->active()->orderBy('id')->first();
    }
}
