<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Enums\FeeAppliesTo;
use App\Modules\Finances\Enums\FeeBilling;
use App\Modules\Finances\Policies\FinanceSettingsPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type de frais (inscription, scolarité, tenue…).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property FeeBilling $billing
 * @property FeeAppliesTo $applies_to
 * @property bool $is_optional
 * @property int $position
 */
#[UsePolicy(FinanceSettingsPolicy::class)]
#[Fillable(['code', 'name', 'billing', 'applies_to', 'is_optional', 'position'])]
class FeeType extends Model implements TracksUsage
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'applies_to' => 'all',
        'is_optional' => false,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'billing' => FeeBilling::class,
            'applies_to' => FeeAppliesTo::class,
            'is_optional' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FeeAmount, $this>
     */
    public function amounts(): HasMany
    {
        return $this->hasMany(FeeAmount::class);
    }

    public function isInUse(): bool
    {
        return $this->amounts()->exists() || InvoiceLine::query()->where('fee_type_id', $this->id)->exists();
    }
}
