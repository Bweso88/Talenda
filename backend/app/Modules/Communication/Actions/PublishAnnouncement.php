<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Models\User;
use App\Modules\Communication\Enums\AnnouncementAudience;
use App\Modules\Communication\Enums\NotificationType;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Communication\Support\AnnouncementAudienceQuery;
use App\Modules\Communication\Support\FamilyContacts;
use App\Modules\Communication\Support\ParentNotifier;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Publie une annonce : notification dans l'app pour chaque parent qui l'a,
 * et SMS (un par famille) si demandé.
 */
final class PublishAnnouncement
{
    public function __construct(
        private readonly ParentNotifier $notifier,
        private readonly SendSms $sendSms,
        private readonly RecordAudit $recordAudit,
    ) {}

    public function handle(string $title, string $body, AnnouncementAudience $audience, ?int $targetId, bool $sendSms, User $author): Announcement
    {
        $title = trim($title);
        $body = trim($body);

        if ($audience !== AnnouncementAudience::School && $targetId === null) {
            throw new BusinessRuleViolation('Choisissez à qui s\'adresse l\'annonce.');
        }

        $students = AnnouncementAudienceQuery::students($audience, $audience === AnnouncementAudience::School ? null : $targetId);

        if ($students->isEmpty()) {
            throw new BusinessRuleViolation('Aucun élève inscrit dans ce public : l\'annonce ne toucherait personne.');
        }

        return DB::transaction(function () use ($title, $body, $audience, $targetId, $sendSms, $author, $students): Announcement {
            $announcement = Announcement::create([
                'title' => $title,
                'body' => $body,
                'audience' => $audience,
                'audience_id' => $audience === AnnouncementAudience::School ? null : $targetId,
                'audience_label' => AnnouncementAudienceQuery::label($audience, $targetId),
                'send_sms' => $sendSms,
                'created_by' => $author->id,
                'published_at' => now(),
            ]);

            $appUsers = $this->notifier->notifyUsers(FamilyContacts::appUserIds($students), NotificationType::Announcement, $title, $body, ['announcement' => $announcement->uuid]);
            $phones = FamilyContacts::smsPhones($students);
            $sms = 0;

            if ($sendSms) {
                foreach (array_keys($phones) as $phone) {
                    $sms += $this->sendSms->handle($phone, self::smsText($title, $body), SmsPurpose::Announcement, $announcement, $author)->segments ?? 0;
                }
            }

            $announcement->update(['families_count' => count($phones), 'app_recipients_count' => $appUsers, 'sms_count' => $sms]);

            $this->recordAudit->handle('announcement.published', actor: $author, subject: $announcement, metadata: [
                'audience' => $announcement->audience_label,
                'sms' => $sms,
            ]);

            return $announcement;
        });
    }

    public static function smsText(string $title, string $body): string
    {
        return FamilyContacts::schoolName().' : '.trim($title).'. '.trim($body);
    }
}
