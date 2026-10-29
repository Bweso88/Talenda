<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Core\Http\Resources\MembershipResource;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * École active (résolue par l'en-tête X-Tenant) et rôles de l'utilisateur
 * dans cette école.
 */
final class CurrentTenantController
{
    public function __invoke(Request $request, TenantContext $tenantContext): MembershipResource
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Tenant $tenant */
        $tenant = $tenantContext->get();

        return new MembershipResource($user->membershipFor($tenant)?->load(['tenant', 'user']));
    }
}
