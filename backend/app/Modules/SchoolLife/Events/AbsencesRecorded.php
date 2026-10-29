<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Events;

use App\Modules\SchoolLife\Models\AttendanceSession;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Nouvelles absences relevées à un appel (élèves qui n'étaient pas déjà
 * notés absents à ce créneau) : les familles sont prévenues (module
 * Communication). Corriger un appel ne prévient pas deux fois.
 */
final class AbsencesRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $enrollmentIds
     */
    public function __construct(
        public readonly AttendanceSession $session,
        public readonly array $enrollmentIds,
    ) {}
}
