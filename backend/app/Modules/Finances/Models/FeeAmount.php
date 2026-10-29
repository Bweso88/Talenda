<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Policies\FinanceSettingsPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de la grille tarifaire : montant d'un type de frais pour un niveau
 * et une année.
 *
 * @property int $id
 * @property int $school_year_id
 * @property int $level_id
 * @property int $fee_type_id
 * @property int $amount
 * @property SchoolYear $schoolYear
 * @property Level $level
 * @property FeeType $feeType
 */
#[UsePolicy(FinanceSettingsPolicy::class)]
#[Fillable(['school_year_id', 'level_id', 'fee_type_id', 'amount'])]
class FeeAmount extends Model implements TracksUsage
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
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
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * @return BelongsTo<FeeType, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * Une ligne de grille se supprime librement : les factures déjà émises
     * gardent leur propre copie du montant.
     */
    public function isInUse(): bool
    {
        return false;
    }
}
