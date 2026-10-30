<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Modules\Assessments\Events\TermPublished;
use App\Modules\Communication\Enums\NotificationType;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Jobs\NotifyReportCards;
use App\Modules\Communication\Models\CommunicationSetting;
use App\Modules\Communication\Sms\SmsText;
use App\Modules\Communication\Support\FamilyContacts;
use App\Modules\Communication\Support\ParentNotifier;
use App\Modules\Communication\Support\Wording;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Finances\Events\PaymentRecorded;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Events\AbsencesRecorded;
use App\Modules\SchoolLife\Events\JustificationReviewed;
use Illuminate\Support\Carbon;

/**
 * Messages automatiques aux familles. Dans l'app : toujours (gratuit) ;
 * par SMS : selon les réglages de l'école (payant).
 */
final class NotifyFamilies
{
    public function __construct(private readonly ParentNotifier $notifier) {}

    public function paymentRecorded(PaymentRecorded $event): void
    {
        $payment = $event->payment->loadMissing('invoice.student.guardians');
        $student = $payment->invoice->student;
        $name = Wording::firstName($student);
        $balance = max(0, $payment->invoice->statement()->balance());
        $amount = SmsText::money($payment->amount);

        $this->notifier->notify(
            $student,
            NotificationType::Payment,
            'Paiement reçu',
            "{$amount} pour {$name} (reçu {$payment->receipt_number}). Reste à payer : ".SmsText::money($balance).'.',
            ['tab' => 'finances'],
            sms: CommunicationSetting::current()->sms_on_payment
                ? FamilyContacts::schoolName()." : paiement de {$amount} enregistré pour {$name}, ref. {$payment->receipt_number}. Reste à payer : ".SmsText::money($balance).'.'
                : null,
            purpose: SmsPurpose::Payment,
            subject: $payment,
        );
    }

    public function absencesRecorded(AbsencesRecorded $event): void
    {
        $session = $event->session->loadMissing(['slot', 'schoolClass']);
        $day = Carbon::parse($session->held_on)->locale('fr');
        $withSms = CommunicationSetting::current()->sms_on_absence;
        $school = FamilyContacts::schoolName();

        Enrollment::query()
            ->whereIn('id', $event->enrollmentIds)
            ->with('student.guardians')
            ->get()
            ->each(function (Enrollment $enrollment) use ($session, $day, $withSms, $school): void {
                $student = $enrollment->student;
                $name = Wording::firstName($student);
                $when = mb_strtolower($session->slot->name).' du '.$day->isoFormat('dddd D MMMM');

                $this->notifier->notify(
                    $student,
                    NotificationType::Absence,
                    "{$name} ".Wording::absent($student),
                    ucfirst($when)." ({$session->schoolClass->name}). Si l'absence est justifiée, envoyez un justificatif depuis l'app.",
                    ['tab' => 'attendance'],
                    sms: $withSms
                        ? "{$school} : {$name} {$student->last_name} ({$session->schoolClass->name}) est ".Wording::absent($student)." le {$day->format('d/m')}, {$session->slot->name}. Merci de justifier l'absence auprès de l'école."
                        : null,
                    purpose: SmsPurpose::Absence,
                    subject: $enrollment,
                );
            });
    }

    public function termPublished(TermPublished $event): void
    {
        NotifyReportCards::dispatch((int) app(TenantContext::class)->id(), $event->term->id);
    }

    /**
     * Seul le parent qui a déposé le justificatif est prévenu.
     */
    public function justificationReviewed(JustificationReviewed $event): void
    {
        $justification = $event->justification->loadMissing('enrollment.student');

        if ($justification->recorded_by === null || $justification->recorded_by === $justification->reviewed_by) {
            return;
        }

        $accepted = $justification->status === JustificationStatus::Accepted;
        $name = Wording::firstName($justification->enrollment->student);
        $period = $justification->starts_on->equalTo($justification->ends_on)
            ? 'du '.$justification->starts_on->format('d/m/Y')
            : 'du '.$justification->starts_on->format('d/m/Y').' au '.$justification->ends_on->format('d/m/Y');

        $this->notifier->notifyUsers(
            [$justification->recorded_by],
            NotificationType::Justification,
            $accepted ? 'Justificatif accepté' : 'Justificatif refusé',
            "Absence {$period} ".Wording::of($name).' : '.($accepted ? 'l\'école a accepté le justificatif.' : 'l\'école a refusé le justificatif. Contactez la vie scolaire.'),
            ['child' => $justification->enrollment->student->uuid, 'tab' => 'attendance'],
        );
    }
}
