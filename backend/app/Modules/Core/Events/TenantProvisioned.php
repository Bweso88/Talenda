<?php

declare(strict_types=1);

namespace App\Modules\Core\Events;

use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une école vient d'être créée. Chaque module y installe ses réglages par
 * défaut (Finances : types de frais et caisse principale).
 */
final class TenantProvisioned
{
    use Dispatchable;

    public function __construct(public readonly Tenant $tenant) {}
}
