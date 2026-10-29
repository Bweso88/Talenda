<?php

declare(strict_types=1);

namespace App\Modules\Academic\Events;

use App\Modules\Academic\Models\SchoolYear;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * L'année suivante a été préparée à partir de l'année précédente. Écouté
 * par Finances pour reprendre la grille tarifaire et les plans de paiement.
 */
final class NextSchoolYearPrepared
{
    use Dispatchable;

    public function __construct(
        public readonly SchoolYear $previous,
        public readonly SchoolYear $next,
    ) {}
}
