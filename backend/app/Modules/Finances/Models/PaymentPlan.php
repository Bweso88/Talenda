<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Policies\FinanceSettingsPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rythme de paiement de la scolarité proposé aux familles pour une année
 * (mensuel, trimestriel, annuel…).
 *
 * @property int $id
 * @property int $school_year_id
 * @property string $name
 * @property bool $is_default
 * @property SchoolYear $schoolYear
 * @property Collection<int, PaymentPlanInstallment> $installments
 */
#[UsePolicy(FinanceSettingsPolicy::class)]
#[Fillable(['school_year_id', 'name', 'is_default'])]
class PaymentPlan extends Model implements TracksUsage
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * @return HasMany<PaymentPlanInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class)->orderBy('position');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isInUse(): bool
    {
        return $this->invoices()->exists();
    }
}
