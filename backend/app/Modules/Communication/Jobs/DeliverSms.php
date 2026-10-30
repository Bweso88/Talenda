<?php

declare(strict_types=1);

namespace App\Modules\Communication\Jobs;

use App\Modules\Communication\Enums\SmsStatus;
use App\Modules\Communication\Models\SmsMessage;
use App\Modules\Communication\Sms\SmsDeliveryFailed;
use App\Modules\Communication\Sms\SmsGateway;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Remet un SMS journalisé au fournisseur, en file d'attente : l'écran ne
 * patiente pas pendant l'envoi.
 */
final class DeliverSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  string|null  $body  texte réel quand celui du journal est masqué
     *                             (mot de passe provisoire) : envoi immédiat seulement
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $smsMessageId,
        public readonly string $sender,
        public readonly ?string $body = null,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SmsGateway $gateway, TenantContext $tenantContext): void
    {
        $tenantContext->run(Tenant::query()->findOrFail($this->tenantId), function () use ($gateway): void {
            $sms = SmsMessage::query()->find($this->smsMessageId);

            if ($sms === null || $sms->status === SmsStatus::Sent) {
                return;
            }

            try {
                $id = $gateway->send($sms->phone, $this->body ?? $sms->body, $this->sender);
                $sms->update(['status' => SmsStatus::Sent, 'provider_message_id' => $id, 'sent_at' => now(), 'error' => null]);
            } catch (SmsDeliveryFailed $exception) {
                $sms->update(['status' => SmsStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 255)]);
            }
        });
    }
}
