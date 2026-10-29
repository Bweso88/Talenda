<?php

declare(strict_types=1);

namespace App\Modules\Finances\Support;

/**
 * Affichage des montants. Les montants sont stockés en entiers, dans la plus
 * petite unité de la devise (le franc pour XAF, qui n'a pas de centimes).
 */
final class Money
{
    private const CURRENCY_LABELS = [
        'XAF' => 'FCFA',
        'XOF' => 'FCFA',
    ];

    public static function format(int $amount, string $currency = 'XAF'): string
    {
        $label = self::CURRENCY_LABELS[$currency] ?? $currency;

        // Espace insécable fine comme séparateur des milliers : 150 000 FCFA.
        return number_format($amount, 0, ',', "\u{202F}")."\u{00A0}".$label;
    }
}
