<?php

use App\Models\User;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Actions\ProvisionTenant;
use App\Modules\Core\Actions\SyncRolesAndPermissions;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\FeeType;
use Filament\Facades\Filament;
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

/**
 * Données minimales d'un nouvel élève pour EnrollNewStudent.
 *
 * @return array<string, mixed>
 */
function newStudentData(array $overrides = []): array
{
    return [
        'last_name' => 'Mabiala',
        'first_names' => 'Grâce Divine',
        'gender' => 'F',
        'birth_date' => '2014-05-12',
        'birth_place' => 'Brazzaville',
        ...$overrides,
    ];
}

/**
 * Se place dans le back-office de l'école, comme le ferait le navigateur.
 */
function actInSchool(User $user, Tenant $school): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel('school');
    Filament::setTenant($school);
    app(TenantContext::class)->set($school);
}

/**
 * Grille tarifaire minimale pour un niveau de l'année ouverte :
 * inscription (nouveaux élèves) et scolarité.
 *
 * @param  array<string, int>  $amounts  code du type de frais => montant
 */
function feeGrid(string $levelCode, array $amounts = ['INSCRIPTION' => 25000, 'SCOLARITE' => 180000]): void
{
    $yearId = SchoolYear::query()->open()->value('id');
    $levelId = Level::firstWhere('code', $levelCode)->id;

    foreach ($amounts as $code => $amount) {
        FeeAmount::create([
            'school_year_id' => $yearId,
            'level_id' => $levelId,
            'fee_type_id' => FeeType::firstWhere('code', $code)->id,
            'amount' => $amount,
        ]);
    }
}
