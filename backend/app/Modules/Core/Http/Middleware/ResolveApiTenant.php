<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Middleware;

use App\Models\User;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * API mobile : l'école active est indiquée par l'en-tête `X-Tenant`
 * (uuid de l'école), et n'est retenue que si l'utilisateur authentifié y a
 * une appartenance active (docs/PRODUCT_ARCHITECTURE.md §7).
 *
 * Une école inconnue et une école où l'utilisateur n'a pas accès renvoient
 * la même réponse 404 : l'API ne révèle pas l'existence des autres écoles.
 */
final class ResolveApiTenant
{
    public const HEADER = 'X-Tenant';

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantUuid = (string) $request->header(self::HEADER);

        abort_if($tenantUuid === '', Response::HTTP_BAD_REQUEST, 'En-tête '.self::HEADER.' manquant.');

        /** @var User $user */
        $user = $request->user();

        $tenant = Str::isUuid($tenantUuid)
            ? Tenant::query()->where('uuid', $tenantUuid)->first()
            : null;

        abort_if(
            $tenant === null || $user->membershipFor($tenant) === null,
            Response::HTTP_NOT_FOUND,
            'École introuvable.',
        );

        $this->tenantContext->set($tenant);

        return $next($request);
    }
}
