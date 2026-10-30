<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

use Filament\Support\Contracts\HasLabel;

enum SmsPurpose: string implements HasLabel
{
    case Credentials = 'credentials';
    case Absence = 'absence';
    case Payment = 'payment';
    case PaymentReminder = 'payment_reminder';
    case ReportCard = 'report_card';
    case Announcement = 'announcement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Credentials => 'Accès à l\'app',
            self::Absence => 'Absence',
            self::Payment => 'Paiement reçu',
            self::PaymentReminder => 'Relance d\'impayé',
            self::ReportCard => 'Bulletin publié',
            self::Announcement => 'Annonce',
        };
    }
}
