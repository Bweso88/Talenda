<?php

declare(strict_types=1);

namespace App\Modules\Finances\Support;

use App\Modules\Finances\Enums\FeeBilling;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\InvoiceLine;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Models\PaymentPlanInstallment;
use Illuminate\Support\Carbon;

/**
 * Situation d'une facture à une date donnée : total, payé, solde,
 * échéancier et retards.
 *
 * L'échéancier n'est pas stocké, il est recalculé ici :
 * 1. les frais « à l'inscription » forment une première échéance, due à la
 *    date d'inscription ;
 * 2. les frais « selon le plan » (la scolarité, réductions comprises) sont
 *    répartis selon les pourcentages du plan de paiement de la famille,
 *    arrondis au franc, le reste d'arrondi allant à la dernière échéance ;
 * 3. une échéance antérieure à l'inscription (élève arrivé en cours
 *    d'année) devient due le jour de l'inscription : elle reste due, mais
 *    l'élève n'est pas « en retard » dès son premier jour ;
 * 4. les paiements valides sont affectés aux échéances dans l'ordre
 *    chronologique (la plus ancienne d'abord, l'inscription en premier).
 *
 * Ainsi une réduction accordée en cours d'année ou un passage du mensuel au
 * trimestriel donnent immédiatement un échéancier juste, sans données à
 * mettre à jour.
 */
final class InvoiceStatement
{
    /**
     * @param  list<StatementInstallment>  $installments
     */
    private function __construct(
        public readonly int $total,
        public readonly int $paid,
        public readonly array $installments,
    ) {}

    /**
     * @param  Payment|null  $upTo  ne compter que les paiements enregistrés
     *                              jusqu'à celui-ci (situation imprimée sur
     *                              son reçu, identique à chaque réimpression)
     */
    public static function for(Invoice $invoice, ?Carbon $today = null, ?Payment $upTo = null): self
    {
        $today = ($today ?? now())->copy()->startOfDay();

        $upfront = 0;
        $spread = 0;

        $invoice->lines->each(function (InvoiceLine $line) use (&$upfront, &$spread): void {
            if ($line->billing === FeeBilling::Upfront) {
                $upfront += $line->amount;
            } else {
                $spread += $line->amount;
            }
        });

        // Une réduction plus forte que les frais d'inscription se reporte
        // sur la scolarité.
        if ($upfront < 0) {
            $spread += $upfront;
            $upfront = 0;
        }

        $schedule = [];
        $enrolledOn = $invoice->enrollment->enrolled_on->copy()->startOfDay();

        if ($upfront > 0) {
            $schedule[] = ['label' => 'À l\'inscription', 'due_on' => $enrolledOn, 'amount' => $upfront];
        }

        foreach (self::spread(max(0, $spread), $invoice, $enrolledOn) as $item) {
            $schedule[] = $item;
        }

        // Tri stable : à date égale, l'inscription reste en premier.
        usort($schedule, fn (array $a, array $b): int => $a['due_on'] <=> $b['due_on']);

        $paid = (int) $invoice->payments
            ->filter(fn (Payment $payment): bool => $payment->isValid() && ($upTo === null || $payment->id <= $upTo->id))
            ->sum('amount');

        $remainingToAllocate = $paid;
        $installments = [];

        foreach ($schedule as $item) {
            $allocated = min($item['amount'], $remainingToAllocate);
            $remainingToAllocate -= $allocated;

            $installments[] = new StatementInstallment(
                label: $item['label'],
                dueOn: $item['due_on'],
                amount: $item['amount'],
                paid: $allocated,
                isOverdue: $allocated < $item['amount'] && $item['due_on']->lt($today),
            );
        }

        return new self(
            total: $upfront + max(0, $spread),
            paid: $paid,
            installments: $installments,
        );
    }

    /**
     * @return list<array{label: string, due_on: Carbon, amount: int}>
     */
    private static function spread(int $amount, Invoice $invoice, Carbon $enrolledOn): array
    {
        if ($amount === 0) {
            return [];
        }

        $planInstallments = $invoice->paymentPlan === null ? collect() : $invoice->paymentPlan->installments;

        if ($planInstallments->isEmpty()) {
            return [['label' => 'Scolarité', 'due_on' => $enrolledOn, 'amount' => $amount]];
        }

        $items = [];
        $allocated = 0;
        $last = $planInstallments->count() - 1;

        foreach ($planInstallments->values() as $index => $installment) {
            /** @var PaymentPlanInstallment $installment */
            $share = $index === $last
                ? $amount - $allocated
                : (int) round($amount * (float) $installment->percentage / 100);

            $allocated += $share;

            $items[] = [
                'label' => $installment->label,
                'due_on' => $installment->due_on->copy()->startOfDay()->max($enrolledOn),
                'amount' => $share,
            ];
        }

        return $items;
    }

    public function balance(): int
    {
        return $this->total - $this->paid;
    }

    /**
     * Montant échu et non payé.
     */
    public function overdue(): int
    {
        return array_sum(array_map(
            fn (StatementInstallment $installment): int => $installment->isOverdue ? $installment->remaining() : 0,
            $this->installments,
        ));
    }

    public function nextDue(): ?StatementInstallment
    {
        foreach ($this->installments as $installment) {
            if (! $installment->isSettled()) {
                return $installment;
            }
        }

        return null;
    }

    public function isSettled(): bool
    {
        return $this->balance() <= 0;
    }
}
