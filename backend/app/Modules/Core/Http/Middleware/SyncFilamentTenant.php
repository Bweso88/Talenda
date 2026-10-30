<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office école : recopie l'école choisie dans l'URL (/ecole/{slug}),
 * déjà validée par Filament via User::canAccessTenant(), dans le
 * TenantContext qui filtre toutes les requêtes Eloquent.
 */
final class SyncFilamentTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Tenant) {
            abort_unless($tenant->status->allowsAccess(), Response::HTTP_FORBIDDEN, 'Cette école est suspendue.');

            $this->tenantContext->set($tenant);
        }

        return $next($request);
    }
}
