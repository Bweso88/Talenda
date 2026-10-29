<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_plan_id
 * @property int $position
 * @property string $label
 * @property Carbon $due_on
 * @property string $percentage
 */
#[Fillable(['payment_plan_id', 'position', 'label', 'due_on', 'percentage'])]
class PaymentPlanInstallment extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'percentage' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PaymentPlan, $this>
     */
    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }
}
