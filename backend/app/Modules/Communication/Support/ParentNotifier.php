<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support;

use App\Modules\Communication\Actions\SendSms;
use App\Modules\Communication\Enums\NotificationType;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Models\DeviceToken;
use App\Modules\Communication\Models\UserNotification;
use App\Modules\Communication\Push\PushGateway;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\Eloquent\Model;

/**
 * Prévient les familles d'un élève : notification dans l'app (et push)
 * pour chaque responsable qui a l'app, SMS au contact principal si
 * demandé.
 */
final class ParentNotifier
{
    public function __construct(
        private readonly SendSms $sendSms,
        private readonly PushGateway $push,
    ) {}

    /**
     * @param  array<string, string>  $data  ex. ['child' => uuid] pour ouvrir la bonne fiche
     * @return array{app: int, sms: int}
     */
    public function notify(
        Student $student,
        NotificationType $type,
        string $title,
        string $body,
        array $data = [],
        ?string $sms = null,
        ?SmsPurpose $purpose = null,
        ?Model $subject = null,
        ?Model $actor = null,
    ): array {
        $student->loadMissing('guardians');
        $users = $this->notifyUsers(FamilyContacts::appUserIds([$student]), $type, $title, $body, ['child' => $student->uuid, ...$data]);

        $sent = 0;
        $phone = $sms === null ? null : FamilyContacts::smsPhone($student);

        if ($phone !== null && $purpose !== null && $this->sendSms->handle($phone, $sms, $purpose, $subject ?? $student, $actor) !== null) {
            $sent = 1;
        }

        return ['app' => $users, 'sms' => $sent];
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<string, string>  $data
     */
    public function notifyUsers(array $userIds, NotificationType $type, string $title, string $body, array $data = []): int
    {
        foreach ($userIds as $userId) {
            UserNotification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
        }

        if ($userIds !== []) {
            $tokens = DeviceToken::query()->whereIn('user_id', $userIds)->pluck('token')->all();
            $this->push->send($tokens, $title, $body, ['type' => $type->value, ...$data]);
        }

        return count($userIds);
    }
}
