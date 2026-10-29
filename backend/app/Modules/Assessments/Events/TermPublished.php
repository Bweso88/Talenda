<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Events;

use App\Modules\Academic\Models\Term;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Bulletins d'une période publiés : les familles sont prévenues (module
 * Communication).
 */
final class TermPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Term $term) {}
}
