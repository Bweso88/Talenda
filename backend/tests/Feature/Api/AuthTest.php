<?php

use App\Models\User;
use App\Modules\Core\Enums\MembershipStatus;
use App\Modules\Core\Models\Tenant;

beforeEach(function () {
    $this->schoolA = Tenant::factory()->create(['name' => 'A — Lycée de Brazzaville']);
    $this->schoolB = Tenant::factory()->create(['name' => 'B — Collège de Pointe-Noire']);

    $this->parent = User::factory()
        ->memberOf($this->schoolA, 'parent')
        ->memberOf($this->schoolB, 'parent')
        ->create(['phone' => '+242061111111', 'email' => 'parent@example.cg', 'password' => 'secret-parent-1']);
});

function login(string $login, string $password = 'secret-parent-1')
{
    return test()->postJson('/api/v1/auth/login', [
        'login' => $login,
        'password' => $password,
        'device_name' => 'Tecno Spark',
    ]);
}

it('connecte un parent avec son numéro au format local', function () {
    login('06 111 11 11')
        ->assertOk()
        ->assertJsonPath('user.uuid', $this->parent->uuid)
        ->assertJsonStructure(['token']);
});

it('connecte un parent avec son e-mail', function () {
    login('PARENT@example.cg')->assertOk();
});

it('refuse un mauvais mot de passe sans préciser ce qui est faux', function () {
    login('061111111', 'mauvais')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['login' => 'Identifiants incorrects.']);
});

it('limite les tentatives de connexion', function () {
    foreach (range(1, 5) as $attempt) {
        login('061111111', 'mauvais')->assertUnprocessable();
    }

    login('061111111', 'mauvais')->assertTooManyRequests();
});

it('liste les écoles du parent avec ses rôles dans chacune', function () {
    $token = login('061111111')->json('token');

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonCount(2, 'memberships')
        ->assertJsonPath('memberships.0.tenant.uuid', $this->schoolA->uuid)
        ->assertJsonPath('memberships.0.tenant.currency', 'XAF')
        ->assertJsonPath('memberships.0.roles', ['parent'])
        ->assertJsonPath('memberships.1.tenant.uuid', $this->schoolB->uuid);
});

it('masque les écoles suspendues et les appartenances suspendues', function () {
    $this->schoolA->update(['status' => 'suspended']);
    $schoolC = Tenant::factory()->create();
    $this->parent->memberships()->create(['tenant_id' => $schoolC->id, 'status' => MembershipStatus::Suspended]);

    $this->actingAs($this->parent)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonCount(1, 'memberships')
        ->assertJsonPath('memberships.0.tenant.uuid', $this->schoolB->uuid);
});

it('révoque le jeton à la déconnexion', function () {
    $token = login('061111111')->json('token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($this->parent->tokens()->count())->toBe(0);
});

describe('école active (en-tête X-Tenant)', function () {
    it('exige l\'en-tête', function () {
        $this->actingAs($this->parent)->getJson('/api/v1/tenant')->assertBadRequest();
    });

    it('renvoie l\'école demandée et les rôles qui y sont attribués', function () {
        $this->actingAs($this->parent)
            ->withHeader('X-Tenant', $this->schoolB->uuid)
            ->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.tenant.uuid', $this->schoolB->uuid)
            ->assertJsonPath('data.roles', ['parent']);
    });

    it('répond 404 pour une école où l\'utilisateur n\'a pas accès', function () {
        $otherSchool = Tenant::factory()->create();

        $this->actingAs($this->parent)
            ->withHeader('X-Tenant', $otherSchool->uuid)
            ->getJson('/api/v1/tenant')
            ->assertNotFound();
    });

    it('répond 404 pour un identifiant inconnu ou mal formé', function (string $tenantUuid) {
        $this->actingAs($this->parent)
            ->withHeader('X-Tenant', $tenantUuid)
            ->getJson('/api/v1/tenant')
            ->assertNotFound();
    })->with(['0195f0c4-0000-7000-8000-000000000000', 'pas-un-uuid', '1']);

    it('répond 404 pour une école suspendue', function () {
        $this->schoolB->update(['status' => 'suspended']);

        $this->actingAs($this->parent)
            ->withHeader('X-Tenant', $this->schoolB->uuid)
            ->getJson('/api/v1/tenant')
            ->assertNotFound();
    });
});

it('refuse l\'accès sans jeton', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
