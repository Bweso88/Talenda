<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Actions;

use App\Models\User;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\Term;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Support\SchoolClock;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use App\Modules\SchoolLife\Models\AttendanceSession;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use App\Modules\SchoolLife\Support\ClassRoll;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre l'appel d'une classe pour un créneau. Refaire l'appel du même
 * créneau le corrige : seuls les absents et les retardataires restent
 * enregistrés.
 */
final class TakeAttendance
{
    /**
     * @param  array<int, array{status?: AttendanceStatus|string|null, late_minutes?: mixed, note?: string|null}>  $entries  par id d'inscription
     */
    public function handle(SchoolClass $schoolClass, Carbon $date, AttendanceSlot $slot, array $entries, User $user): AttendanceSession
    {
        $date = $date->copy()->startOfDay();
        $this->ensureDateIsOpen($schoolClass, $date);

        if ($user->cannot('takeAttendance', [AttendanceRecord::class, $schoolClass, $date])) {
            throw new BusinessRuleViolation('Vous ne pouvez faire l\'appel que de vos classes, le jour même.');
        }

        $roll = ClassRoll::on($schoolClass, $date)->modelKeys();

        foreach (array_keys($entries) as $enrollmentId) {
            if (! in_array((int) $enrollmentId, $roll, true)) {
                throw new BusinessRuleViolation('Un élève de l\'appel n\'était pas inscrit dans cette classe à cette date.');
            }
        }

        return DB::transaction(function () use ($schoolClass, $date, $slot, $entries, $user): AttendanceSession {
            $session = AttendanceSession::query()->firstOrNew([
                'school_class_id' => $schoolClass->id,
                'held_on' => $date->toDateString(),
                'attendance_slot_id' => $slot->id,
            ]);

            if (! $session->exists) {
                $session->duration_minutes = $slot->durationMinutes();
            }

            $session->taken_by = $user->id;
            $session->save();
            $session->touch();

            foreach ($entries as $enrollmentId => $entry) {
                $status = self::status($entry['status'] ?? null);
                $lateMinutes = (int) ($entry['late_minutes'] ?? 0);

                // Des minutes de retard saisies suffisent à noter le retard.
                if ($status === AttendanceStatus::Present && $lateMinutes > 0) {
                    $status = AttendanceStatus::Late;
                }

                $keys = ['attendance_session_id' => $session->id, 'enrollment_id' => (int) $enrollmentId];

                if ($status === AttendanceStatus::Present) {
                    AttendanceRecord::query()->where($keys)->delete();

                    continue;
                }

                AttendanceRecord::query()->updateOrCreate($keys, [
                    'status' => $status,
                    'late_minutes' => $status === AttendanceStatus::Late && $lateMinutes > 0 ? $lateMinutes : null,
                    'note' => blank($entry['note'] ?? null) ? null : trim((string) $entry['note']),
                ]);
            }

            return $session;
        });
    }

    private function ensureDateIsOpen(SchoolClass $schoolClass, Carbon $date): void
    {
        $schoolClass->loadMissing('schoolYear');
        $year = $schoolClass->schoolYear;

        if ($year->status !== SchoolYearStatus::Open) {
            throw new BusinessRuleViolation("L'année {$year->name} n'est pas ouverte : appel impossible.");
        }

        if ($date->lt($year->starts_on) || $date->gt($year->ends_on)) {
            throw new BusinessRuleViolation("Le {$date->format('d/m/Y')} est en dehors de l'année scolaire {$year->name}.");
        }

        if ($date->gt(SchoolClock::today())) {
            throw new BusinessRuleViolation('On ne fait pas l\'appel d\'un jour qui n\'est pas encore arrivé.');
        }

        $term = Term::query()
            ->where('school_year_id', $year->id)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();

        if ($term?->status === TermStatus::Published) {
            throw new BusinessRuleViolation("Les bulletins de « {$term->name} » sont publiés : les absences de cette période ne se modifient plus.");
        }
    }

    /**
     * Le statut arrive en texte (API) ou en énumération (formulaire).
     */
    private static function status(AttendanceStatus|string|null $status): AttendanceStatus
    {
        return $status instanceof AttendanceStatus ? $status : (AttendanceStatus::tryFrom((string) $status) ?? AttendanceStatus::Present);
    }
}
