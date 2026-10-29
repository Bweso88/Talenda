<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Modules\Core\Support\SequenceGenerator;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\PaymentPlan;
use Illuminate\Support\Facades\DB;

/**
 * Facture créée automatiquement à chaque inscription ou réinscription
 * (docs/PRODUCT_ARCHITECTURE.md §9) : frais obligatoires de la grille du
 * niveau, plan de paiement par défaut de l'année. Sans effet si
 * l'inscription a déjà sa facture.
 */
final class CreateEnrollmentInvoice
{
    public function __construct(
        private readonly SequenceGenerator $sequenceGenerator,
        private readonly ApplyFeeGrid $applyFeeGrid,
    ) {}

    public function handle(Enrollment $enrollment): Invoice
    {
        $existing = Invoice::query()->where('enrollment_id', $enrollment->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($enrollment): Invoice {
            $enrollment->loadMissing('schoolYear');
            $startYear = $enrollment->schoolYear->starts_on->year;

            $invoice = Invoice::create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'school_year_id' => $enrollment->school_year_id,
                'payment_plan_id' => PaymentPlan::query()
                    ->where('school_year_id', $enrollment->school_year_id)
                    ->where('is_default', true)
                    ->value('id'),
                'number' => sprintf('F%d-%05d', $startYear, $this->sequenceGenerator->next("invoice:{$startYear}")),
            ]);

            $this->applyFeeGrid->handle($invoice);

            return $invoice;
        });
    }
}
