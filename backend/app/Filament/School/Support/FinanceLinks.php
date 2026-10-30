<?php

namespace App\Filament\School\Support;

use App\Modules\Finances\Models\Payment;
use Filament\Facades\Filament;

final class FinanceLinks
{
    public static function receiptUrl(Payment $payment): string
    {
        return route('filament.school.receipt', [
            'tenant' => Filament::getTenant()?->getRouteKey(),
            'payment' => $payment->uuid,
        ]);
    }
}
