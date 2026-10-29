<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

/**
 * Normalisation des numéros de téléphone au format E.164 (+242061234567),
 * format unique de stockage et de comparaison.
 *
 * Volontairement simple : elle couvre les gabarits de pays déclarés dans
 * config/talenda.php. Si Talenda s'ouvre à de nombreux pays, remplacer par
 * libphonenumber.
 */
final class PhoneNumber
{
    /**
     * Retourne le numéro au format E.164, ou null s'il n'est pas valide.
     *
     * Formats acceptés pour le Congo : « 06 123 45 67 », « 061234567 »,
     * « +242 06 123 45 67 », « 00242061234567 ».
     */
    public static function normalize(string $input, ?string $countryCode = null): ?string
    {
        $country = CountryPresets::get($countryCode ?? CountryPresets::defaultCode());

        if ($country === null) {
            return null;
        }

        $digits = preg_replace('/[\s.\-()]/', '', trim($input)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        $dialCode = $country['dial_code'];
        $length = $country['national_number_length'];

        if (str_starts_with($digits, '+')) {
            $international = substr($digits, 1);

            if (! str_starts_with($international, $dialCode)) {
                return null;
            }

            $digits = substr($international, strlen($dialCode));
        }

        if (preg_match('/^\d{'.$length.'}$/', $digits) !== 1) {
            return null;
        }

        return '+'.$dialCode.$digits;
    }
}
