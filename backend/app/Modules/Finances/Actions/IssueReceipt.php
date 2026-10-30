<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Modules\Core\Documents\SchoolDocumentRenderer;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\InvoiceStatement;
use LogicException;

/**
 * Reçu de paiement en PDF (format A5), avec la situation de la facture
 * juste après ce paiement : un reçu réimprimé plus tard reste identique.
 */
final class IssueReceipt
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SchoolDocumentRenderer $renderer,
    ) {}

    public function handle(Payment $payment): string
    {
        $tenant = $this->tenantContext->get() ?? throw new LogicException('Aucune école active.');

        $payment->loadMissing(['invoice.student', 'invoice.enrollment.schoolClass', 'invoice.schoolYear', 'recorder', 'reverser']);

        return $this->renderer->render($tenant, 'documents.payment-receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'statement' => InvoiceStatement::for($payment->invoice->loadMissing(['lines', 'payments', 'paymentPlan.installments']), $payment->paid_on, $payment),
            'currency' => $tenant->currency,
        ], paper: 'a5');
    }
}
