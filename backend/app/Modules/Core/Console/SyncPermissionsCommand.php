<?php

declare(strict_types=1);

namespace App\Modules\Core\Console;

use App\Modules\Core\Actions\SyncRolesAndPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('talenda:sync-permissions')]
#[Description('Aligne les rôles et permissions en base sur config/talenda.php')]
final class SyncPermissionsCommand extends Command
{
    public function handle(SyncRolesAndPermissions $syncRolesAndPermissions): int
    {
        $syncRolesAndPermissions->handle();

        $this->components->info('Rôles et permissions synchronisés.');

        return self::SUCCESS;
    }
}
