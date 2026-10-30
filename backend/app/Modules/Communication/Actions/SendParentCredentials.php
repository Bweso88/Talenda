<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Models\SmsMessage;
use App\Modules\Communication\Support\FamilyContacts;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Enrollments\Models\Guardian;
use Illuminate\Database\Eloquent\Model;

/**
 * Envoie au parent, par SMS, son accès à l'app. Le mot de passe provisoire
 * est envoyé immédiatement et masqué dans le journal SMS.
 */
final class SendParentCredentials
{
    public function __construct(private readonly SendSms $sendSms) {}

    /**
     * @param  string|null  $temporaryPassword  null si le parent avait déjà un compte
     */
    public function handle(Guardian $guardian, ?string $temporaryPassword, ?Model $actor = null): ?SmsMessage
    {
        if ($guardian->phone === null) {
            return null;
        }

        $intro = FamilyContacts::schoolName()." : votre accès à l'app Talenda Parents est ouvert. Identifiant : ".PhoneNumber::format($guardian->phone).'.';

        if ($temporaryPassword === null) {
            return $this->sendSms->handle($guardian->phone, "{$intro} Utilisez votre mot de passe habituel.", SmsPurpose::Credentials, $guardian, $actor);
        }

        $text = "{$intro} Mot de passe provisoire : %s (à changer à la première connexion).";

        return $this->sendSms->handle(
            $guardian->phone,
            sprintf($text, $temporaryPassword),
            SmsPurpose::Credentials,
            $guardian,
            $actor,
            loggedBody: sprintf($text, '••••••'),
        );
    }
}
