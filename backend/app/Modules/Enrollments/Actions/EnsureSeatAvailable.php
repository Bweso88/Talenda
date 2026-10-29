<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Actions;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Exceptions\BusinessRuleViolation;

/**
 * Règles communes à toute entrée dans une classe : année non clôturée et
 * place disponible.
 */
final class EnsureSeatAvailable
{
    public function handle(SchoolClass $schoolClass): void
    {
        if ($schoolClass->schoolYear->isClosed()) {
            throw new BusinessRuleViolation("L'année {$schoolClass->schoolYear->name} est clôturée.");
        }

        if ($schoolClass->isFull()) {
            throw new BusinessRuleViolation("La classe {$schoolClass->name} est complète ({$schoolClass->capacity} places).");
        }
    }
}
