<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Import;

use Illuminate\Support\Str;

/**
 * Colonnes du fichier d'import des élèves, dans l'ordre du modèle
 * téléchargeable. Les en-têtes sont reconnus sans tenir compte des
 * majuscules, des accents ni des espaces.
 */
final class StudentImportColumns
{
    /**
     * @var array<string, string> clé interne => en-tête du fichier
     */
    public const COLUMNS = [
        'last_name' => 'Nom',
        'first_names' => 'Prénoms',
        'gender' => 'Sexe (F/M)',
        'birth_date' => 'Date de naissance (JJ/MM/AAAA)',
        'birth_place' => 'Lieu de naissance',
        'class' => 'Classe',
        'guardian_last_name' => 'Nom du responsable',
        'guardian_first_names' => 'Prénoms du responsable',
        'guardian_phone' => 'Téléphone du responsable',
        'guardian_relationship' => 'Lien (Père/Mère/Tuteur/Autre)',
        'previous_school' => 'Ancien établissement',
    ];

    /**
     * @var list<string>
     */
    public const REQUIRED = ['last_name', 'first_names', 'gender', 'birth_date', 'class'];

    /**
     * Retrouve la clé interne d'un en-tête saisi librement
     * (« date de naissance », « DATE NAISSANCE »…).
     */
    public static function keyForHeader(string $header): ?string
    {
        $normalized = self::normalize($header);

        foreach (self::COLUMNS as $key => $label) {
            $expected = self::normalize($label);

            if ($normalized === $expected || $normalized === self::normalize(Str::before($label, '('))) {
                return $key;
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        return (string) preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($value)));
    }
}
