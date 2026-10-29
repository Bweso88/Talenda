<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Finances\Enums\PaymentStatus;
use App\Modules\Finances\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Annulation d'un encaissement (erreur de saisie, chèque rejeté…). Le
 * paiement reste visible, marqué annulé, avec l'auteur et le motif.
 *
 * Séparation des tâches : la personne qui a encaissé ne peut pas annuler
 * son propre encaissement.
 */
final class ReversePayment
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    public function handle(Payment $payment, string $reason, User $user): void
    {
        if (! $payment->isValid()) {
            throw new BusinessRuleViolation("Le reçu {$payment->receipt_number} est déjà annulé.");
        }

        if ($payment->recorded_by === $user->id) {
            throw new BusinessRuleViolation('Vous ne pouvez pas annuler un encaissement que vous avez vous-même enregistré.');
        }

        if (blank($reason)) {
            throw new BusinessRuleViolation('Indiquez le motif de l\'annulation.');
        }

        DB::transaction(function () use ($payment, $reason, $user): void {
            $payment->update([
                'status' => PaymentStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $user->id,
                'reversal_reason' => trim($reason),
            ]);

            $this->recordAudit->handle('payment.reversed', actor: $user, subject: $payment, metadata: [
                'receipt' => $payment->receipt_number,
                'amount' => $payment->amount,
                'reason' => $reason,
            ]);
        });
    }
}
