<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Modules\Finances\Enums\InvoiceLineKind;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\FeeType;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Model;

/**
 * Ajoute à une facture les frais obligatoires de la grille qui n'y sont pas
 * encore (par exemple quand la grille a été saisie après les premières
 * inscriptions). Ne modifie jamais une ligne existante.
 */
final class ApplyFeeGrid
{
    /**
     * @return int nombre de lignes ajoutées
     */
    public function handle(Invoice $invoice, ?Model $actor = null): int
    {
        $invoice->loadMissing('enrollment.schoolClass');
        $enrollment = $invoice->enrollment;

        $alreadyBilled = $invoice->lines()->whereNotNull('fee_type_id')->pluck('fee_type_id')->all();

        $amounts = FeeAmount::query()
            ->with('feeType')
            ->where('school_year_id', $invoice->school_year_id)
            ->where('level_id', $enrollment->schoolClass->level_id)
            ->whereNotIn('fee_type_id', $alreadyBilled)
            ->get()
            ->filter(fn (FeeAmount $feeAmount): bool => ! $feeAmount->feeType->is_optional
                && $feeAmount->feeType->applies_to->matches($enrollment->type))
            ->sortBy(fn (FeeAmount $feeAmount): int => $feeAmount->feeType->position);

        foreach ($amounts as $feeAmount) {
            /** @var FeeType $feeType */
            $feeType = $feeAmount->feeType;

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'kind' => InvoiceLineKind::Fee,
                'fee_type_id' => $feeType->id,
                'billing' => $feeType->billing,
                'label' => $feeType->name,
                'amount' => $feeAmount->amount,
                'created_by' => $actor?->getKey(),
            ]);
        }

        $invoice->unsetRelation('lines');

        return $amounts->count();
    }
}
