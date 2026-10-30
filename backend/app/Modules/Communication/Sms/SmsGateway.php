<?php

declare(strict_types=1);

namespace App\Modules\Communication\Sms;

/**
 * Fournisseur d'envoi de SMS. Le fournisseur réel (agrégateur couvrant
 * MTN et Airtel au Congo) se branche ici sans toucher au reste : il suffit
 * d'une classe de plus et de la variable SMS_DRIVER.
 */
interface SmsGateway
{
    public function name(): string;

    /**
     * @param  string  $phone  numéro international (+242…)
     * @return string identifiant du message chez le fournisseur
     *
     * @throws SmsDeliveryFailed
     */
    public function send(string $phone, string $body, string $sender): string;
}
