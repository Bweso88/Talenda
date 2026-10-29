<?php

declare(strict_types=1);

namespace App\Modules\Finances\Events;

use App\Modules\Finances\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Encaissement enregistré. Servira à envoyer le reçu au parent
 * (notifications, étape 5).
 */
final class PaymentRecorded
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
