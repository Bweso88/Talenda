<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
final class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'country_code' => $this->country_code,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'status' => $this->status->value,
        ];
    }
}
