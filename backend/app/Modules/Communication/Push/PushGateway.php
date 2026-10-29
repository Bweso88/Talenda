<?php

declare(strict_types=1);

namespace App\Modules\Communication\Push;

/**
 * Envoi des notifications push vers les téléphones. Le pilote Firebase
 * (FCM) se branchera ici quand le projet Firebase de Talenda existera.
 */
interface PushGateway
{
    /**
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void;
}
