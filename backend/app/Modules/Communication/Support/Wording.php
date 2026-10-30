<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support;

use App\Modules\Enrollments\Models\Student;

/**
 * Petites règles de français pour les messages automatiques.
 */
final class Wording
{
    /**
     * « d'Arsène », « de Grâce ».
     */
    public static function of(string $name): string
    {
        return (preg_match('/^[aeiouyhàâéèêëîïôûüAEIOUYHÀÂÉÈÊËÎÏÔÛÜ]/u', $name) === 1 ? "d'" : 'de ').$name;
    }

    /**
     * Premier prénom : « Grâce » pour « Grâce Divine ».
     */
    public static function firstName(Student $student): string
    {
        return explode(' ', trim($student->first_names))[0];
    }

    /**
     * « absent » ou « absente ».
     */
    public static function absent(Student $student): string
    {
        return $student->gender->value === 'F' ? 'absente' : 'absent';
    }
}
