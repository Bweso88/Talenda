<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Membership;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Membership
 */
final class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'tenant' => new TenantResource($this->tenant),
            // Les rôles dépendent de l'école : on les lit dans son contexte.
            'roles' => app(TenantContext::class)->run(
                $this->tenant,
                fn () => $this->user->roles()->pluck('name')->sort()->values(),
            ),
        ];
    }
}
