<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

/**
 * Accès typé aux gabarits de pays de config/talenda.php.
 *
 * @phpstan-type CountryPreset array{name: string, currency: string, timezone: string, locale: string, dial_code: string, national_number_length: int}
 */
final class CountryPresets
{
    /**
     * @return CountryPreset|null
     */
    public static function get(string $countryCode): ?array
    {
        /** @var array<string, CountryPreset> $countries */
        $countries = config('talenda.countries', []);

        return $countries[$countryCode] ?? null;
    }

    public static function defaultCode(): string
    {
        return (string) config('talenda.default_country');
    }

    /**
     * Liste « code => nom du pays » pour les listes déroulantes.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        /** @var array<string, CountryPreset> $countries */
        $countries = config('talenda.countries', []);

        return array_map(fn (array $country): string => $country['name'], $countries);
    }
}
