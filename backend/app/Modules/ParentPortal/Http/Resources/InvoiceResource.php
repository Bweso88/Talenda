<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Resources;

use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Support\StatementInstallment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Facture d'une année : situation, échéancier calculé et paiements.
 * Montants en francs CFA (entiers).
 *
 * @mixin Invoice
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statement = $this->statement();

        return [
            'uuid' => $this->uuid,
            'number' => $this->number,
            'school_year' => $this->schoolYear->name,
            'class_name' => $this->enrollment->schoolClass->name,
            'payment_plan' => $this->paymentPlan?->name,
            'total' => $statement->total,
            'paid' => $statement->paid,
            'balance' => max(0, $statement->balance()),
            'overdue' => $statement->overdue(),
            'installments' => array_map(fn (StatementInstallment $installment): array => [
                'label' => $installment->label,
                'due_on' => $installment->dueOn->toDateString(),
                'amount' => $installment->amount,
                'paid' => $installment->paid,
                'remaining' => max(0, $installment->remaining()),
                'is_overdue' => $installment->isOverdue && ! $installment->isSettled(),
                'status_label' => $installment->statusLabel(),
            ], $statement->installments),
            'payments' => PaymentResource::collection($this->payments->sortByDesc('paid_on')->values()),
        ];
    }
}
