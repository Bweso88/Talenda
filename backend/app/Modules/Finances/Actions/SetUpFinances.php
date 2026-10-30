<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\ExpenseCategory;
use App\Modules\Finances\Models\FeeType;
use App\Modules\Finances\Models\PaymentPlan;
use App\Modules\Finances\Models\PaymentPlanInstallment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Réglages financiers par défaut, tirés du gabarit du pays
 * (config/talenda.php) : types de frais, caisse principale, plans de
 * paiement d'une année. Tout reste modifiable par l'école.
 */
final class SetUpFinances
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function forNewTenant(Tenant $tenant): void
    {
        $this->tenantContext->run($tenant, function () use ($tenant): void {
            /** @var list<array{code: string, name: string, billing: string, applies_to: string, optional: bool}> $feeTypes */
            $feeTypes = config("talenda.academic_presets.{$tenant->country_code}.fee_types", []);

            foreach ($feeTypes as $position => $feeType) {
                FeeType::query()->firstOrCreate(['code' => $feeType['code']], [
                    'name' => $feeType['name'],
                    'billing' => $feeType['billing'],
                    'applies_to' => $feeType['applies_to'],
                    'is_optional' => $feeType['optional'],
                    'position' => $position + 1,
                ]);
            }

            CashRegister::query()->firstOrCreate(['name' => 'Caisse principale']);

            foreach (config('talenda.accounting.expense_categories', []) as $position => [$name, $accountCode]) {
                ExpenseCategory::query()->firstOrCreate(['name' => $name], ['account_code' => $accountCode, 'position' => $position + 1]);
            }

            // Années créées avant que Finances ne soit prêt (provisionnement).
            SchoolYear::query()->get()->each(fn (SchoolYear $schoolYear) => $this->paymentPlansFor($schoolYear));
        });
    }

    /**
     * Plans de paiement du gabarit pour l'année (sans effet si l'année en
     * a déjà). Les échéances tombent dans l'année scolaire : « Janvier »
     * d'une année 2026-2027 est en janvier 2027.
     */
    public function paymentPlansFor(SchoolYear $schoolYear): void
    {
        $tenant = $this->tenantContext->get();

        if ($tenant === null || PaymentPlan::query()->where('school_year_id', $schoolYear->id)->exists()) {
            return;
        }

        /** @var array<string, list<array{0: string, 1: int, 2: int, 3: float|int}>> $plans */
        $plans = config("talenda.academic_presets.{$tenant->country_code}.payment_plans", []);
        $defaultPlan = config("talenda.academic_presets.{$tenant->country_code}.default_payment_plan");
        $startYear = $schoolYear->starts_on->year;
        $startMonth = $schoolYear->starts_on->month;

        DB::transaction(function () use ($plans, $defaultPlan, $schoolYear, $startYear, $startMonth): void {
            foreach ($plans as $name => $installments) {
                $plan = PaymentPlan::create([
                    'school_year_id' => $schoolYear->id,
                    'name' => $name,
                    'is_default' => $name === $defaultPlan,
                ]);

                foreach ($installments as $position => [$label, $month, $day, $percentage]) {
                    PaymentPlanInstallment::create([
                        'payment_plan_id' => $plan->id,
                        'position' => $position + 1,
                        'label' => $label,
                        'due_on' => Carbon::create($month >= $startMonth ? $startYear : $startYear + 1, $month, $day),
                        'percentage' => $percentage,
                    ]);
                }
            }
        });
    }
}
