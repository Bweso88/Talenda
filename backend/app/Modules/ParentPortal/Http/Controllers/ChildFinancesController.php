<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Controllers;

use App\Models\User;
use App\Modules\Finances\Actions\IssueReceipt;
use App\Modules\Finances\Enums\PaymentStatus;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\Payment;
use App\Modules\ParentPortal\Http\Resources\InvoiceResource;
use App\Modules\ParentPortal\Support\GuardianChildren;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Scolarité : factures par année (la plus récente d'abord), échéancier,
 * paiements et reçus.
 */
final class ChildFinancesController
{
    public function index(Request $request, string $child): AnonymousResourceCollection
    {
        $student = GuardianChildren::findOrFail($this->user($request), $child);

        $invoices = Invoice::query()
            ->where('student_id', $student->id)
            ->with(['schoolYear', 'enrollment.schoolClass', 'paymentPlan.installments', 'lines', 'payments'])
            ->get()
            ->sortByDesc(fn (Invoice $invoice): string => $invoice->schoolYear->starts_on->toDateString())
            ->values();

        return InvoiceResource::collection($invoices);
    }

    /**
     * Reçu PDF d'un paiement, identique à celui remis au guichet.
     */
    public function receipt(Request $request, string $child, string $payment, IssueReceipt $issueReceipt): Response
    {
        $student = GuardianChildren::findOrFail($this->user($request), $child);

        $record = Payment::query()
            ->where('uuid', $payment)
            ->where('status', PaymentStatus::Valid)
            ->whereHas('invoice', fn ($query) => $query->where('student_id', $student->id))
            ->firstOrFail();

        return response($issueReceipt->handle($record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu-'.$record->receipt_number.'.pdf"',
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
