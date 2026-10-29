<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Policies\FinanceSettingsPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Caisse physique (guichet). Une école peut en avoir plusieurs.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property CashSession|null $openSession
 */
#[UsePolicy(FinanceSettingsPolicy::class)]
#[Fillable(['name', 'is_active'])]
class CashRegister extends Model implements TracksUsage
{
    use BelongsToTenant;

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
     * @return HasMany<CashSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /**
     * @return HasOne<CashSession, $this>
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->whereNull('closed_at');
    }

    public function isInUse(): bool
    {
        return $this->sessions()->exists();
    }
}
