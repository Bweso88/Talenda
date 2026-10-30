<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Middleware;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Integrations\Models\IntegrationClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API d'intégration : la clé (Bearer) identifie à la fois l'application
 * partenaire et l'école ; aucune autre école n'est accessible.
 */
final class AuthenticateIntegration
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();

        $client = $token === '' ? null : $this->tenantContext->withoutTenant(fn () => IntegrationClient::query()
            ->where('token_hash', IntegrationClient::hash($token))
            ->whereNull('revoked_at')
            ->first());

        abort_if($client === null, Response::HTTP_UNAUTHORIZED, 'Clé d\'intégration invalide ou révoquée.');

        $this->tenantContext->set(Tenant::query()->findOrFail($client->tenant_id));

        if ($client->last_used_at === null || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('integration_client', $client);

        return $next($request);
    }
}
