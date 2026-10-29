<?php

declare(strict_types=1);

namespace App\Modules\Academic\Events;

use App\Modules\Academic\Models\SchoolYear;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Nouvelle année scolaire créée (avec ses périodes). Écouté par Finances
 * pour proposer les plans de paiement de l'année.
 */
final class SchoolYearCreated
{
    use Dispatchable;

    public function __construct(public readonly SchoolYear $schoolYear) {}
}
