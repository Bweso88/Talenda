<?php

declare(strict_types=1);

namespace App\Modules\Communication\Push;

use Illuminate\Support\Facades\Log;

/**
 * Pilote de développement : la notification reste dans l'app (liste des
 * notifications), le push est seulement journalisé.
 */
final class LogPushGateway implements PushGateway
{
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        if ($tokens !== []) {
            Log::info('Push (non envoyé, pilote « log ») vers '.count($tokens)." appareil(s) : {$title} — {$body}");
        }
    }
}
