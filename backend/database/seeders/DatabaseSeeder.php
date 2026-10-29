<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Core\Actions\ProvisionTenant;
use App\Modules\Core\Actions\SyncRolesAndPermissions;
use App\Modules\Core\Models\PlatformAdmin;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration : deux écoles congolaises et leurs comptes,
 * pour vérifier à la main que chaque école ne voit que ses données.
 *
 * Mot de passe de tous les comptes de démo : « talenda-demo ».
 */
class DatabaseSeeder extends Seeder
{
    public function run(SyncRolesAndPermissions $syncRolesAndPermissions, ProvisionTenant $provisionTenant): void
    {
        $syncRolesAndPermissions->handle();

        PlatformAdmin::query()->firstOrCreate(
            ['email' => 'admin@talenda.test'],
            ['name' => 'Super Admin Talenda', 'password' => 'talenda-demo'],
        );

        $brazzaville = Tenant::query()->firstWhere('slug', 'lycee-demo-brazzaville')
            ?? $provisionTenant->handle(
                school: ['name' => 'Lycée Démo de Brazzaville', 'slug' => 'lycee-demo-brazzaville'],
                admin: ['name' => 'Directeur Brazzaville', 'email' => 'directeur@brazzaville.test', 'phone' => '+242061111111', 'password' => 'talenda-demo'],
            );

        $pointeNoire = Tenant::query()->firstWhere('slug', 'college-demo-pointe-noire')
            ?? $provisionTenant->handle(
                school: ['name' => 'Collège Démo de Pointe-Noire', 'slug' => 'college-demo-pointe-noire'],
                admin: ['name' => 'Directrice Pointe-Noire', 'email' => 'directrice@pointe-noire.test', 'phone' => '+242052222222', 'password' => 'talenda-demo'],
            );

        // Un parent avec un enfant dans chaque école : un seul compte,
        // deux appartenances.
        if (! User::query()->where('phone', '+242063333333')->exists()) {
            User::factory()
                ->memberOf($brazzaville, 'parent')
                ->memberOf($pointeNoire, 'parent')
                ->create([
                    'name' => 'Parent Démo',
                    'phone' => '+242063333333',
                    'email' => null,
                    'password' => 'talenda-demo',
                ]);
        }

        User::query()
            ->whereIn('phone', ['+242061111111', '+242052222222', '+242063333333'])
            ->update(['must_change_password' => false]);

        app(TenantContext::class)->set(null);
    }
}
