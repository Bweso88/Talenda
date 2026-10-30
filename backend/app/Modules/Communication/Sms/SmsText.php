<?php

declare(strict_types=1);

namespace App\Modules\Communication\Sms;

/**
 * Longueur facturée d'un SMS. Un SMS fait 160 caractères de l'alphabet
 * GSM (dont é, è, à, ù), mais seulement 70 dès qu'un caractère en sort
 * (ê, ô, î, émoticônes…) ; un message long est découpé en plusieurs SMS
 * facturés (153 ou 67 caractères chacun).
 */
final class SmsText
{
    private const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXTENDED = '^{}\\[~]|€';

    /**
     * Lettres accentuées hors alphabet GSM remplacées par la plus proche :
     * un seul « â » (Grâce) ferait passer tout le SMS à 70 caractères et
     * doublerait son coût. é, è, à, ù font partie de l'alphabet GSM et
     * sont conservées.
     */
    private const TRANSLITERATION = [
        'â' => 'a', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'û' => 'u', 'ç' => 'c', 'œ' => 'oe', 'ÿ' => 'y',
        'Â' => 'A', 'À' => 'A', 'Ê' => 'E', 'È' => 'E', 'Ë' => 'E', 'Î' => 'I', 'Ï' => 'I', 'Ô' => 'O', 'Û' => 'U', 'Ù' => 'U', 'Œ' => 'OE',
        '’' => "'", '‘' => "'", '–' => '-', '—' => '-', '…' => '...',
    ];

    /**
     * Texte prêt à envoyer : espaces insécables et guillemets français
     * simplifiés, accents hors alphabet GSM remplacés.
     */
    public static function clean(string $text): string
    {
        $text = (string) preg_replace(['/«[\s\x{00A0}\x{202F}]*/u', '/[\s\x{00A0}\x{202F}]*»/u'], '"', $text);
        $text = (string) preg_replace('/[\x{00A0}\x{202F}\x{2009}]/u', ' ', $text);

        return trim(strtr($text, self::TRANSLITERATION));
    }

    public static function isGsm(string $text): bool
    {
        foreach (mb_str_split($text) as $character) {
            if (! str_contains(self::GSM, $character) && ! str_contains(self::GSM_EXTENDED, $character)) {
                return false;
            }
        }

        return true;
    }

    public static function segments(string $text): int
    {
        if (self::isGsm($text)) {
            $length = mb_strlen($text) + preg_match_all('/['.preg_quote(self::GSM_EXTENDED, '/').']/u', $text);

            return $length <= 160 ? 1 : (int) ceil($length / 153);
        }

        $length = mb_strlen($text);

        return $length <= 70 ? 1 : (int) ceil($length / 67);
    }

    /**
     * 50000 → « 50 000 FCFA » (espaces simples).
     */
    public static function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' FCFA';
    }
}
