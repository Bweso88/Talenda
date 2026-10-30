<?php

declare(strict_types=1);

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Inscrit une action sensible au journal d'audit
 * (docs/PRODUCT_ARCHITECTURE.md §14).
 */
final class RecordAudit
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        string $action,
        ?Model $actor = null,
        ?Model $subject = null,
        array $metadata = [],
        ?int $tenantId = null,
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => $tenantId ?? $this->tenantContext->id(),
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
