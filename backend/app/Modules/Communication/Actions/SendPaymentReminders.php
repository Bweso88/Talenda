<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Models\User;
use App\Modules\Communication\Enums\NotificationType;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Models\SmsMessage;
use App\Modules\Communication\Sms\SmsText;
use App\Modules\Communication\Support\FamilyContacts;
use App\Modules\Communication\Support\ParentNotifier;
use App\Modules\Communication\Support\Wording;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Support\UnpaidRow;

/**
 * Relance des impayés : SMS au responsable qui paie (et notification dans
 * l'app). Une famille n'est pas relancée deux fois le même jour pour la
 * même facture.
 */
final class SendPaymentReminders
{
    public function __construct(
        private readonly ParentNotifier $notifier,
        private readonly SendSms $sendSms,
    ) {}

    /**
     * @param  iterable<UnpaidRow>  $rows
     * @return array{sent: int, skipped: int}
     */
    public function handle(iterable $rows, User $actor): array
    {
        $sent = 0;
        $skipped = 0;
        $school = FamilyContacts::schoolName();

        foreach ($rows as $row) {
            if ($row->overdue <= 0 || $row->phone === null || self::remindedToday($row->invoice)) {
                $skipped++;

                continue;
            }

            $invoice = $row->invoice->loadMissing(['student.guardians', 'enrollment.schoolClass']);
            $student = $invoice->student;
            $name = Wording::firstName($student);
            $amount = SmsText::money($row->overdue);

            $this->notifier->notifyUsers(
                FamilyContacts::appUserIds([$student]),
                NotificationType::PaymentReminder,
                'Rappel de paiement',
                'La scolarité '.Wording::of($name)." présente un retard de {$amount}. Merci de régulariser auprès de la caisse.",
                ['child' => $student->uuid, 'tab' => 'finances'],
            );

            $this->sendSms->handle(
                $row->phone,
                "{$school} : rappel, la scolarité ".Wording::of("{$name} {$student->last_name}")." ({$invoice->enrollment->schoolClass->name}) présente un retard de {$amount}. Merci de régulariser auprès de la caisse.",
                SmsPurpose::PaymentReminder,
                $invoice,
                $actor,
            );
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    public static function remindedToday(Invoice $invoice): bool
    {
        return SmsMessage::query()
            ->where('purpose', SmsPurpose::PaymentReminder)
            ->where('subject_type', $invoice->getMorphClass())
            ->where('subject_id', $invoice->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();
    }
}
