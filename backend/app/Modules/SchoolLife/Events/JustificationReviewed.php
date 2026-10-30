<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Events;

use App\Modules\SchoolLife\Models\AbsenceJustification;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Justificatif déposé par un parent, accepté ou refusé par l'école.
 */
final class JustificationReviewed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly AbsenceJustification $justification) {}
}
