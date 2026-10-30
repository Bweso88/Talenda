<?php

declare(strict_types=1);

namespace App\Modules\Core\Auth;

use App\Modules\Core\Support\PhoneNumber;

/**
 * Traduit l'identifiant saisi à la connexion (e-mail ou téléphone) en
 * critère de recherche de l'utilisateur. Partagé par le back-office web et
 * l'API mobile pour qu'ils acceptent exactement les mêmes saisies.
 */
final class LoginIdentifier
{
    /**
     * @return array{email: string}|array{phone: string}|null
     */
    public static function toCredentials(string $login): ?array
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return ['email' => mb_strtolower($login)];
        }

        $phone = PhoneNumber::normalize($login);

        return $phone === null ? null : ['phone' => $phone];
    }
}
