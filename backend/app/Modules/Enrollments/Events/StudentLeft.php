<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Events;

use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un élève a quitté l'école en cours d'année (départ ou transfert).
 */
final class StudentLeft
{
    use Dispatchable;

    public function __construct(public readonly Enrollment $enrollment) {}
}
