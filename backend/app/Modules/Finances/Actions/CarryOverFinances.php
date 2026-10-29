<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\PaymentPlan;
use App\Modules\Finances\Models\PaymentPlanInstallment;
use Illuminate\Support\Facades\DB;

/**
 * Préparation de l'année suivante : reprend la grille tarifaire et les
 * plans de paiement de l'année précédente (échéances décalées d'un an).
 * L'école ajuste ensuite les montants si les tarifs changent.
 */
final class CarryOverFinances
{
    public function handle(SchoolYear $previous, SchoolYear $next): void
    {
        DB::transaction(function () use ($previous, $next): void {
            FeeAmount::query()->where('school_year_id', $previous->id)->get()
                ->each(fn (FeeAmount $feeAmount) => FeeAmount::query()->firstOrCreate(
                    ['school_year_id' => $next->id, 'level_id' => $feeAmount->level_id, 'fee_type_id' => $feeAmount->fee_type_id],
                    ['amount' => $feeAmount->amount],
                ));

            $previousPlans = PaymentPlan::query()->where('school_year_id', $previous->id)->with('installments')->get();

            if ($previousPlans->isEmpty()) {
                return;
            }

            // Les plans du gabarit créés avec l'année cèdent la place à ceux
            // que l'école avait ajustés.
            PaymentPlan::query()->where('school_year_id', $next->id)->get()
                ->each(fn (PaymentPlan $plan) => $plan->isInUse() ? null : $plan->delete());

            foreach ($previousPlans as $previousPlan) {
                if (PaymentPlan::query()->where('school_year_id', $next->id)->where('name', $previousPlan->name)->exists()) {
                    continue;
                }

                $plan = PaymentPlan::create([
                    'school_year_id' => $next->id,
                    'name' => $previousPlan->name,
                    'is_default' => $previousPlan->is_default,
                ]);

                foreach ($previousPlan->installments as $installment) {
                    PaymentPlanInstallment::create([
                        'payment_plan_id' => $plan->id,
                        'position' => $installment->position,
                        'label' => $installment->label,
                        'due_on' => $installment->due_on->copy()->addYear(),
                        'percentage' => $installment->percentage,
                    ]);
                }
            }
        });
    }
}
