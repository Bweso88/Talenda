<?php

declare(strict_types=1);

namespace App\Modules\Finances\Http\Controllers;

use App\Modules\Finances\Actions\IssueReceipt;
use App\Modules\Finances\Models\Payment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Reçu PDF d'un paiement, ouvert dans le navigateur pour impression.
 * Route du back-office école : le paiement est cherché dans l'école active
 * (TenantScope), un paiement d'une autre école donne donc 404.
 */
final class ReceiptController
{
    public function __invoke(string $tenant, string $payment, IssueReceipt $issueReceipt): Response
    {
        $record = Payment::query()->where('uuid', $payment)->firstOrFail();

        Gate::authorize('view', $record);

        return response($issueReceipt->handle($record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu-'.$record->receipt_number.'.pdf"',
        ]);
    }
}
