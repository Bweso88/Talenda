<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Support\Collection;

/**
 * Qui prévenir pour un élève :
 * - dans l'app : tous ses responsables qui y ont accès (gratuit) ;
 * - par SMS : un seul numéro par famille, le contact principal (à défaut
 *   celui qui paie, puis le premier responsable avec un téléphone).
 */
final class FamilyContacts
{
    /**
     * @param  iterable<Student>  $students  avec la relation guardians chargée
     * @return list<int> comptes des responsables (sans doublon)
     */
    public static function appUserIds(iterable $students): array
    {
        $ids = [];

        foreach ($students as $student) {
            foreach ($student->guardians as $guardian) {
                if ($guardian->user_id !== null) {
                    $ids[$guardian->user_id] = $guardian->user_id;
                }
            }
        }

        return array_values($ids);
    }

    public static function smsPhone(Student $student): ?string
    {
        /** @var Collection<int, Guardian> $guardians */
        $guardians = $student->guardians->filter(fn (Guardian $guardian): bool => $guardian->phone !== null);

        return ($guardians->first(fn (Guardian $guardian): bool => $guardian->pivot->is_primary_contact ?? false)
            ?? $guardians->first(fn (Guardian $guardian): bool => $guardian->pivot->is_financially_responsible ?? false)
            ?? $guardians->first())?->phone;
    }

    /**
     * Un SMS par numéro : deux enfants d'une même famille = un seul SMS.
     *
     * @param  iterable<Student>  $students
     * @return array<string, Student> premier élève concerné, par numéro
     */
    public static function smsPhones(iterable $students): array
    {
        $phones = [];

        foreach ($students as $student) {
            $phone = self::smsPhone($student);

            if ($phone !== null && ! isset($phones[$phone])) {
                $phones[$phone] = $student;
            }
        }

        return $phones;
    }

    public static function schoolName(): string
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant?->settings?->short_name ?: (string) $tenant?->name;
    }
}
