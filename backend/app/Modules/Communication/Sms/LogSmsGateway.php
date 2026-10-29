<?php

declare(strict_types=1);

namespace App\Modules\Communication\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fournisseur de développement : n'envoie rien, écrit le SMS dans le
 * journal de l'application. Le message apparaît aussi dans le journal SMS
 * du back-office, comme un vrai.
 */
final class LogSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phone, string $body, string $sender): string
    {
        Log::info("SMS (non envoyé, pilote « log ») de {$sender} à {$phone} : {$body}");

        return 'log-'.Str::uuid7();
    }
}
