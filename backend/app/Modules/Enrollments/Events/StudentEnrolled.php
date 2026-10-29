<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Events;

use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un élève vient d'être inscrit ou réinscrit. Écouté par la facturation
 * (étape 3) et, plus tard, par la jonction avec Scolia
 * (docs/PRODUCT_ARCHITECTURE.md §2).
 */
final class StudentEnrolled
{
    use Dispatchable;

    public function __construct(public readonly Enrollment $enrollment) {}
}
