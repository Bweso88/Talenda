<?php

use App\Models\User;
use App\Modules\Core\Actions\ProvisionTenant;
use App\Modules\Core\Actions\SyncRolesAndPermissions;
use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Les tests utilisent PostgreSQL (base talenda_test) : le schéma repose sur
 * des fonctionnalités propres à PostgreSQL (contraintes CHECK, jsonb).
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => app(SyncRolesAndPermissions::class)->handle())
    ->in('Feature');

/**
 * École provisionnée comme en production (structure du Congo, année en
 * cours ouverte), avec son administrateur.
 *
 * @param  list<string>  $cycles
 * @return array{0: Tenant, 1: User}
 */
function provisionSchool(string $slug = 'lycee-test', array $cycles = ['COLLEGE', 'LYCEE']): array
{
    $tenant = app(ProvisionTenant::class)->handle(
        school: ['name' => 'École '.$slug, 'slug' => $slug, 'cycles' => $cycles],
        admin: ['name' => 'Admin '.$slug, 'email' => $slug.'@example.cg', 'password' => 'mot-de-passe-admin'],
    );

    return [$tenant, User::firstWhere('email', $slug.'@example.cg')];
}
