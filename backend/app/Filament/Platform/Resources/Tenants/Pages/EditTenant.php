<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

/**
 * Pas d'action de suppression : on ne supprime jamais les données d'une
 * école, on la suspend (docs/PRODUCT_ARCHITECTURE.md §15).
 */
class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterSave(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->getRecord();

        if ($tenant->wasChanged('status')) {
            app(RecordAudit::class)->handle(
                action: 'tenant.status_changed',
                actor: Filament::auth()->user(),
                subject: $tenant,
                metadata: ['status' => $tenant->status->value],
                tenantId: $tenant->id,
            );
        }
    }
}
