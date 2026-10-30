<?php

declare(strict_types=1);

namespace App\Modules\Finances\Events;

use App\Modules\Finances\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Encaissement enregistré : la famille est prévenue (module
 * Communication), une fois l'encaissement validé en base.
 */
final class PaymentRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
