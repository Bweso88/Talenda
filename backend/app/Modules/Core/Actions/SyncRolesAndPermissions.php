<?php

declare(strict_types=1);

namespace App\Modules\Core\Actions;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aligne les rôles et permissions en base sur le catalogue de
 * config/talenda.php. Idempotent : à lancer à chaque déploiement
 * (php artisan talenda:sync-permissions).
 *
 * Les rôles fournis par défaut sont globaux (tenant_id nul) : les mêmes
 * pour toutes les écoles. Les rôles personnalisés d'une école (V2) seront
 * créés avec son tenant_id.
 */
final class SyncRolesAndPermissions
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(): void
    {
        $this->registrar->forgetCachedPermissions();

        /** @var list<string> $permissions */
        $permissions = config('talenda.permissions');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $previousTeamId = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId(null);

        try {
            /** @var array<string, list<string>|string> $roles */
            $roles = config('talenda.roles');

            foreach ($roles as $roleName => $rolePermissions) {
                $role = Role::query()->firstOrCreate(
                    ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => null],
                );

                $role->syncPermissions($rolePermissions === '*' ? $permissions : $rolePermissions);
            }
        } finally {
            $this->registrar->setPermissionsTeamId($previousTeamId);
            $this->registrar->forgetCachedPermissions();
        }
    }
}
