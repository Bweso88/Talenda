<?php

use App\Models\User;
use App\Modules\Core\Actions\ProvisionTenant;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\PlatformAdmin;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;

function provision(array $school = [], array $admin = [], ?PlatformAdmin $actor = null): Tenant
{
    return app(ProvisionTenant::class)->handle(
        school: [...['name' => 'Lycée Savorgnan', 'slug' => 'lycee-savorgnan'], ...$school],
        admin: [...['name' => 'Mme Ngoy', 'phone' => '+242061234567', 'password' => 'mot-de-passe-provisoire'], ...$admin],
        actor: $actor,
    );
}

it('crée une école avec le gabarit du Congo par défaut', function () {
    $tenant = provision();

    expect($tenant->country_code)->toBe('CG')
        ->and($tenant->currency)->toBe('XAF')
        ->and($tenant->timezone)->toBe('Africa/Brazzaville')
        ->and($tenant->locale)->toBe('fr')
        ->and($tenant->uuid)->toBeString();
});

it('crée le premier administrateur avec changement de mot de passe obligatoire', function () {
    $tenant = provision();

    $admin = User::firstWhere('phone', '+242061234567');

    expect($admin->must_change_password)->toBeTrue()
        ->and($admin->membershipFor($tenant))->not->toBeNull()
        ->and(app(TenantContext::class)->run($tenant, fn () => $admin->hasRole('school_admin')))->toBeTrue();
});

it('n\'attribue le rôle d\'administrateur que dans la nouvelle école', function () {
    $tenant = provision();
    $otherSchool = provision(['name' => 'Autre école', 'slug' => 'autre-ecole'], ['phone' => '+242067654321']);

    $admin = User::firstWhere('phone', '+242061234567');

    expect(app(TenantContext::class)->run($otherSchool, fn () => $admin->roles()->count()))->toBe(0)
        ->and($admin->membershipFor($otherSchool))->toBeNull()
        ->and(app(TenantContext::class)->run($tenant, fn () => $admin->roles()->count()))->toBe(1);
});

it('réutilise le compte existant d\'une personne déjà présente dans une autre école', function () {
    $first = provision();
    $second = provision(['name' => 'Deuxième école', 'slug' => 'deuxieme-ecole']);

    $admin = User::firstWhere('phone', '+242061234567');

    expect(User::count())->toBe(1)
        ->and($admin->accessibleTenants()->pluck('id')->all())->toEqualCanonicalizing([$first->id, $second->id]);
});

it('journalise la création de l\'école', function () {
    $actor = PlatformAdmin::factory()->create();

    $tenant = provision(actor: $actor);

    $log = AuditLog::firstWhere('action', 'tenant.provisioned');

    expect($log->tenant_id)->toBe($tenant->id)
        ->and($log->actor_type)->toBe($actor->getMorphClass())
        ->and($log->actor_id)->toBe($actor->id);
});

it('refuse un pays sans gabarit', function () {
    provision(['country_code' => 'ZZ']);
})->throws(InvalidArgumentException::class);

it('interdit de modifier le journal d\'audit', function () {
    provision();

    AuditLog::first()->update(['action' => 'falsifié']);
})->throws(LogicException::class);
