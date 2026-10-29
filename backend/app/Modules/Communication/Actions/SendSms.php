<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Jobs\DeliverSms;
use App\Modules\Communication\Models\CommunicationSetting;
use App\Modules\Communication\Models\SmsMessage;
use App\Modules\Communication\Sms\SmsGateway;
use App\Modules\Communication\Sms\SmsText;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Journalise un SMS puis le confie au fournisseur (en file d'attente).
 */
final class SendSms
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SmsGateway $gateway,
    ) {}

    /**
     * @param  string|null  $loggedBody  version journalisée quand le texte
     *                                   contient un secret ; l'envoi est alors
     *                                   immédiat, sans passer par la file
     *                                   d'attente (le secret n'est pas stocké)
     */
    public function handle(string $phone, string $body, SmsPurpose $purpose, ?Model $subject = null, ?Model $actor = null, ?string $loggedBody = null): ?SmsMessage
    {
        $tenantId = $this->tenantContext->id() ?? throw new LogicException('Aucune école active.');
        $phone = PhoneNumber::normalize($phone);

        if ($phone === null) {
            return null;
        }

        $body = SmsText::clean($body);

        $sms = SmsMessage::create([
            'phone' => $phone,
            'body' => $loggedBody === null ? $body : SmsText::clean($loggedBody),
            'segments' => SmsText::segments($body),
            'purpose' => $purpose,
            'provider' => $this->gateway->name(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'created_by' => $actor?->getKey(),
        ]);

        $sender = CommunicationSetting::current()->sms_sender ?: (string) config('talenda.sms.default_sender');

        $loggedBody === null
            ? DeliverSms::dispatch($tenantId, $sms->id, $sender)->afterCommit()
            : DeliverSms::dispatchSync($tenantId, $sms->id, $sender, $body);

        return $sms;
    }
}
