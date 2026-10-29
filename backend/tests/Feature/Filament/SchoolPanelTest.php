<?php

use App\Filament\School\Auth\Login;
use App\Models\User;
use App\Modules\Core\Models\Tenant;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->schoolA = Tenant::factory()->create(['slug' => 'ecole-a']);
    $this->schoolB = Tenant::factory()->create(['slug' => 'ecole-b']);

    $this->adminA = User::factory()
        ->memberOf($this->schoolA, 'school_admin')
        ->create(['phone' => '+242061111111', 'password' => 'secret-admin-a']);
});

it('redirige un visiteur vers la connexion', function () {
    $this->get('/ecole/ecole-a')->assertRedirect('/ecole/login');
});

it('ouvre le tableau de bord de l\'école de l\'utilisateur', function () {
    $this->actingAs($this->adminA)
        ->get('/ecole/ecole-a')
        ->assertOk()
        ->assertSee($this->schoolA->name);
});

it('refuse l\'accès au back-office d\'une autre école', function () {
    $this->actingAs($this->adminA)->get('/ecole/ecole-b')->assertNotFound();
});

it('refuse l\'accès à une école suspendue, en gardant l\'accès aux autres', function () {
    $schoolC = Tenant::factory()->create(['slug' => 'ecole-c']);
    $this->adminA->memberships()->create(['tenant_id' => $schoolC->id, 'status' => 'active']);
    $this->schoolA->update(['status' => 'suspended']);

    $this->actingAs($this->adminA)->get('/ecole/ecole-a')->assertNotFound();
    $this->actingAs($this->adminA)->get('/ecole/ecole-c')->assertOk();
});

it('refuse le back-office quand la seule école du compte est suspendue', function () {
    $this->schoolA->update(['status' => 'suspended']);

    $this->actingAs($this->adminA)->get('/ecole/ecole-a')->assertForbidden();
});

it('refuse le back-office à un compte sans école', function () {
    $this->actingAs(User::factory()->create())->get('/ecole')->assertForbidden();
});

it('permet la connexion par téléphone au format local', function () {
    Filament::setCurrentPanel('school');

    Livewire::test(Login::class)
        ->fillForm(['login' => '06 111 11 11', 'password' => 'secret-admin-a'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->adminA);
});

it('refuse un mauvais mot de passe', function () {
    Filament::setCurrentPanel('school');

    Livewire::test(Login::class)
        ->fillForm(['login' => '061111111', 'password' => 'mauvais'])
        ->call('authenticate')
        ->assertHasFormErrors(['login']);

    $this->assertGuest();
});
