<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

enum NotificationType: string
{
    case ReportCard = 'report_card';
    case Absence = 'absence';
    case Payment = 'payment';
    case PaymentReminder = 'payment_reminder';
    case Justification = 'justification';
    case Announcement = 'announcement';
}
