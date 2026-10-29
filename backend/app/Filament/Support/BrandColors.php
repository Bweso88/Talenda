<?php

namespace App\Filament\Support;

/**
 * Couleurs du logo Talenda pour les back-offices : bleu (#1E5AA8) en 600,
 * la nuance des boutons, et bleu marine (#0F2C5C) en 900.
 */
final class BrandColors
{
    /**
     * @return array<int, string>
     */
    public static function primary(): array
    {
        return [
            50 => 'oklch(0.97 0.014 257)',
            100 => 'oklch(0.93 0.032 257)',
            200 => 'oklch(0.87 0.06 257)',
            300 => 'oklch(0.78 0.095 257)',
            400 => 'oklch(0.66 0.13 257)',
            500 => 'oklch(0.56 0.14 257)',
            600 => 'oklch(0.48 0.13 257)',
            700 => 'oklch(0.42 0.12 257)',
            800 => 'oklch(0.36 0.1 258)',
            900 => 'oklch(0.3 0.085 259)',
            950 => 'oklch(0.22 0.06 260)',
        ];
    }
}
