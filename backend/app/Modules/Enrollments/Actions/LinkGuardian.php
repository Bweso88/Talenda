<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Actions;

use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Enrollments\Models\Student;
use App\Modules\Enrollments\Models\StudentGuardian;

/**
 * Rattache un responsable à un élève. Un responsable dont le téléphone est
 * déjà connu de l'école (parent d'un frère ou d'une sœur) est réutilisé
 * tel quel au lieu d'être saisi une deuxième fois.
 *
 * @phpstan-import-type GuardianData from EnrollNewStudent
 */
final class LinkGuardian
{
    /**
     * @param  GuardianData  $guardianData
     */
    public function handle(Student $student, array $guardianData): StudentGuardian
    {
        $guardian = $this->findOrCreateGuardian($guardianData);

        if ($student->guardianLinks()->where('guardian_id', $guardian->id)->exists()) {
            throw new BusinessRuleViolation("{$guardian->full_name} est déjà responsable de cet élève.");
        }

        $isPrimaryContact = (bool) ($guardianData['is_primary_contact'] ?? false);

        if ($isPrimaryContact) {
            $student->guardianLinks()->update(['is_primary_contact' => false]);
        }

        return StudentGuardian::create([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => $guardianData['relationship'],
            // Le premier responsable saisi est le contact principal par défaut.
            'is_primary_contact' => $isPrimaryContact || ! $student->guardianLinks()->exists(),
            'is_financially_responsible' => (bool) ($guardianData['is_financially_responsible'] ?? false),
        ]);
    }

    /**
     * @param  GuardianData  $guardianData
     */
    private function findOrCreateGuardian(array $guardianData): Guardian
    {
        $attributes = array_intersect_key($guardianData, array_flip(['last_name', 'first_names', 'phone', 'email', 'profession', 'address']));

        $guardian = new Guardian($attributes);

        if ($guardian->phone !== null) {
            $existing = Guardian::query()->where('phone', $guardian->phone)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $guardian->save();

        return $guardian;
    }
}
