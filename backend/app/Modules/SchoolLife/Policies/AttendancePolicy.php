<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Policies;

use App\Models\User;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Support\SchoolClock;
use App\Modules\SchoolLife\Support\AttendanceClasses;
use Illuminate\Support\Carbon;

/**
 * Absences et justificatifs. L'enseignant fait l'appel de ses classes, le
 * jour même ; la vie scolaire (surveillant, censeur) fait l'appel de toutes
 * les classes à n'importe quelle date, suit les absences et les justifie.
 */
final class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function takeAttendance(User $user, SchoolClass $schoolClass, Carbon $date): bool
    {
        if ($user->can('attendance.manage')) {
            return true;
        }

        return $user->can('attendance.take')
            && $date->isSameDay(SchoolClock::today())
            && AttendanceClasses::for($user)->whereKey($schoolClass->id)->exists();
    }
}
