<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Support\SequenceGenerator;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Events\PaymentRecorded;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Encaissement (docs/PRODUCT_ARCHITECTURE.md §9) :
 * - les espèces exigent une session de caisse ouverte par la personne qui
 *   encaisse ; les autres moyens y sont rattachés si elle en a une ;
 * - mobile money, virement et chèque exigent une référence vérifiable ;
 * - jamais plus que le reste à payer (pas d'avoir implicite) ;
 * - reçu numéroté sans trou par année civile : R2026-00001.
 */
final class RecordPayment
{
    public function __construct(
        private readonly SequenceGenerator $sequenceGenerator,
        private readonly RecordAudit $recordAudit,
    ) {}

    public function handle(
        Invoice $invoice,
        int $amount,
        PaymentMethod $method,
        User $cashier,
        ?string $reference = null,
        ?string $payerName = null,
        ?Carbon $paidOn = null,
    ): Payment {
        $paidOn ??= now();
        $reference = blank($reference) ? null : trim($reference);

        if ($amount <= 0) {
            throw new BusinessRuleViolation('Le montant doit être positif.');
        }

        if ($paidOn->isFuture()) {
            throw new BusinessRuleViolation('La date de paiement ne peut pas être dans le futur.');
        }

        if ($method->requiresReference() && $reference === null) {
            throw new BusinessRuleViolation("Indiquez la référence du paiement ({$method->getLabel()}).");
        }

        $session = CashDesk::openSessionOf($cashier);

        if ($method === PaymentMethod::Cash && $session === null) {
            throw new BusinessRuleViolation('Ouvrez votre caisse avant d\'encaisser des espèces.');
        }

        return DB::transaction(function () use ($invoice, $amount, $method, $cashier, $reference, $payerName, $paidOn, $session): Payment {
            // Verrou sur la facture : deux encaissements simultanés ne
            // peuvent pas dépasser ensemble le reste à payer.
            Invoice::query()->whereKey($invoice->id)->lockForUpdate()->first();
            $invoice->unsetRelation('payments');

            $balance = $invoice->statement()->balance();

            if ($amount > $balance) {
                throw new BusinessRuleViolation('Le montant dépasse le reste à payer ('.Money::format(max(0, $balance)).').');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'cash_session_id' => $session?->id,
                'receipt_number' => sprintf('R%d-%05d', $paidOn->year, $this->sequenceGenerator->next("receipt:{$paidOn->year}")),
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'payer_name' => blank($payerName) ? null : trim($payerName),
                'paid_on' => $paidOn,
                'recorded_by' => $cashier->id,
            ]);

            $invoice->unsetRelation('payments');

            $this->recordAudit->handle('payment.recorded', actor: $cashier, subject: $payment, metadata: [
                'receipt' => $payment->receipt_number,
                'amount' => $amount,
                'method' => $method->value,
                'invoice' => $invoice->number,
            ]);

            PaymentRecorded::dispatch($payment);

            return $payment;
        });
    }
}
