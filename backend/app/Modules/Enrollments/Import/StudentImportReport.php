<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Import;

/**
 * Résultat de l'analyse d'un fichier d'import : lignes prêtes à importer
 * et erreurs par numéro de ligne. L'import n'a lieu que s'il n'y a aucune
 * erreur.
 *
 * @phpstan-import-type StudentData from \App\Modules\Enrollments\Actions\EnrollNewStudent
 * @phpstan-import-type GuardianData from \App\Modules\Enrollments\Actions\EnrollNewStudent
 */
final class StudentImportReport
{
    /**
     * @param  array<int, array{student: StudentData, guardians: list<GuardianData>, school_class_id: int}>  $rows
     * @param  array<int, list<string>>  $errors
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [] && $this->rows !== [];
    }

    /**
     * Messages prêts à afficher : « Ligne 4 : … » (ligne 0 = erreur qui
     * concerne tout le fichier).
     *
     * @return list<string>
     */
    public function errorMessages(): array
    {
        $messages = [];

        foreach ($this->errors as $line => $lineErrors) {
            foreach ($lineErrors as $error) {
                $messages[] = $line === 0 ? ucfirst($error) : "Ligne {$line} : {$error}";
            }
        }

        return $messages;
    }
}
