<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Actions;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\SchoolLife\Models\AttendanceSlot;

/**
 * Créneaux d'appel par défaut d'une nouvelle école (gabarit du pays).
 */
final class SetUpSchoolLife
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Tenant $tenant): void
    {
        /** @var list<array{name: string, starts_at: string, ends_at: string}> $slots */
        $slots = config("talenda.academic_presets.{$tenant->country_code}.attendance_slots", [
            ['name' => 'Journée', 'starts_at' => '08:00', 'ends_at' => '12:00'],
        ]);

        $this->tenantContext->run($tenant, function () use ($slots): void {
            foreach ($slots as $position => $slot) {
                AttendanceSlot::query()->firstOrCreate(['name' => $slot['name']], [
                    'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'],
                    'position' => $position + 1,
                ]);
            }
        });
    }
}
