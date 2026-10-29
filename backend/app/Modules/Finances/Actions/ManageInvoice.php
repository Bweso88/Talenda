<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Finances\Enums\FeeBilling;
use App\Modules\Finances\Enums\InvoiceLineKind;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\FeeType;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\InvoiceLine;
use App\Modules\Finances\Models\PaymentPlan;
use App\Modules\Finances\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modifications d'une facture, toutes par ajout de lignes (jamais par
 * modification) et toutes journalisées : frais supplémentaire, réduction,
 * correction, annulation du reste dû, changement de plan de paiement.
 */
final class ManageInvoice
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    /**
     * Frais facultatif ou oublié (tenue, examen…). Montant de la grille par
     * défaut.
     */
    public function addFee(Invoice $invoice, FeeType $feeType, ?int $amount = null, ?Model $actor = null): InvoiceLine
    {
        $invoice->loadMissing('enrollment.schoolClass');

        $amount ??= FeeAmount::query()
            ->where('school_year_id', $invoice->school_year_id)
            ->where('level_id', $invoice->enrollment->schoolClass->level_id)
            ->where('fee_type_id', $feeType->id)
            ->value('amount');

        if ($amount === null || $amount <= 0) {
            throw new BusinessRuleViolation("Aucun montant pour « {$feeType->name} » : indiquez-le ou complétez la grille tarifaire.");
        }

        return $this->addLine($invoice, [
            'kind' => InvoiceLineKind::Fee,
            'fee_type_id' => $feeType->id,
            'billing' => $feeType->billing,
            'label' => $feeType->name,
            'amount' => $amount,
        ], 'invoice.fee_added', $actor);
    }

    /**
     * Réduction (fratrie, enfant du personnel, bourse…), répartie sur les
     * échéances de scolarité.
     */
    public function addDiscount(Invoice $invoice, int $amount, string $label, string $reason, ?Model $actor = null): InvoiceLine
    {
        if ($amount <= 0) {
            throw new BusinessRuleViolation('Le montant de la réduction doit être positif.');
        }

        return $this->addLine($invoice, [
            'kind' => InvoiceLineKind::Discount,
            'billing' => FeeBilling::Spread,
            'label' => $label,
            'amount' => -$amount,
            'reason' => $reason,
        ], 'invoice.discount_added', $actor);
    }

    /**
     * Correction libre, positive ou négative, toujours motivée.
     */
    public function addAdjustment(Invoice $invoice, int $amount, string $label, string $reason, ?Model $actor = null): InvoiceLine
    {
        if ($amount === 0) {
            throw new BusinessRuleViolation('Une correction ne peut pas être nulle.');
        }

        return $this->addLine($invoice, [
            'kind' => InvoiceLineKind::Adjustment,
            'billing' => FeeBilling::Spread,
            'label' => $label,
            'amount' => $amount,
            'reason' => $reason,
        ], 'invoice.adjusted', $actor);
    }

    /**
     * Remise du reste dû (départ en cours d'année, geste de la direction).
     * Les sommes déjà payées restent acquises.
     */
    public function cancelRemainingBalance(Invoice $invoice, string $reason, ?Model $actor = null): ?InvoiceLine
    {
        $balance = $invoice->statement()->balance();

        if ($balance <= 0) {
            return null;
        }

        return $this->addAdjustment($invoice, -$balance, 'Remise du reste dû', $reason, $actor);
    }

    public function changePaymentPlan(Invoice $invoice, PaymentPlan $plan, ?Model $actor = null): void
    {
        if ($plan->school_year_id !== $invoice->school_year_id) {
            throw new BusinessRuleViolation('Ce plan de paiement n\'est pas celui de l\'année de la facture.');
        }

        $previous = $invoice->paymentPlan?->name;
        $invoice->update(['payment_plan_id' => $plan->id]);
        $invoice->unsetRelation('paymentPlan');

        $this->recordAudit->handle('invoice.plan_changed', actor: $actor, subject: $invoice, metadata: [
            'from' => $previous,
            'to' => $plan->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addLine(Invoice $invoice, array $attributes, string $auditAction, ?Model $actor): InvoiceLine
    {
        return DB::transaction(function () use ($invoice, $attributes, $auditAction, $actor): InvoiceLine {
            $statement = $invoice->statement();
            $newTotal = $statement->total + $attributes['amount'];

            // Jamais de facture inférieure à ce qui a déjà été encaissé : il
            // faudrait rembourser, ce qui se traite à part.
            if ($newTotal < $statement->paid) {
                throw new BusinessRuleViolation(
                    'Impossible : le total de la facture deviendrait inférieur aux '.Money::format($statement->paid).' déjà payés.',
                );
            }

            $line = InvoiceLine::create([
                ...$attributes,
                'invoice_id' => $invoice->id,
                'created_by' => $actor?->getKey(),
            ]);

            $invoice->unsetRelation('lines');

            $this->recordAudit->handle($auditAction, actor: $actor, subject: $invoice, metadata: [
                'label' => $line->label,
                'amount' => $line->amount,
                'reason' => $line->reason,
            ]);

            return $line;
        });
    }
}
