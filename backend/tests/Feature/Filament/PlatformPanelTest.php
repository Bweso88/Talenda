<?php

use App\Filament\Platform\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Platform\Resources\Tenants\Pages\ListTenants;
use App\Models\User;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\PlatformAdmin;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->platformAdmin = PlatformAdmin::factory()->create();
});

it('réserve le back-office plateforme aux Super Admins', function () {
    $schoolUser = User::factory()->memberOf(Tenant::factory()->create(), 'school_admin')->create();

    $this->actingAs($schoolUser)->get('/platform')->assertRedirect('/platform/login');
    $this->actingAs($this->platformAdmin, 'platform')->get('/platform')->assertOk();
});

it('n\'ouvre pas le back-office école à un Super Admin', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($this->platformAdmin, 'platform')
        ->get("/ecole/{$tenant->slug}")
        ->assertRedirect('/ecole/login');
});

it('liste les écoles', function () {
    $tenants = Tenant::factory()->count(2)->create();

    Filament::setCurrentPanel('platform');
    $this->actingAs($this->platformAdmin, 'platform');

    Livewire::test(ListTenants::class)->assertCanSeeTableRecords($tenants);
});

it('crée une école et son administrateur depuis le back-office', function () {
    Filament::setCurrentPanel('platform');
    $this->actingAs($this->platformAdmin, 'platform');

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Lycée Chaminade',
            'slug' => 'lycee-chaminade',
            'country_code' => 'CG',
            'admin_name' => 'M. Mabiala',
            'admin_phone' => '05 444 44 44',
            'admin_password' => 'provisoire-123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tenant = Tenant::firstWhere('slug', 'lycee-chaminade');
    $admin = User::firstWhere('phone', '+242054444444');

    expect($tenant->currency)->toBe('XAF')
        ->and($admin->must_change_password)->toBeTrue()
        ->and(app(TenantContext::class)->run($tenant, fn () => $admin->hasRole('school_admin')))->toBeTrue()
        ->and(AuditLog::where('action', 'tenant.provisioned')->value('actor_id'))->toBe($this->platformAdmin->id);
});

it('valide le téléphone de l\'administrateur', function () {
    Filament::setCurrentPanel('platform');
    $this->actingAs($this->platformAdmin, 'platform');

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Lycée Chaminade',
            'slug' => 'lycee-chaminade',
            'country_code' => 'CG',
            'admin_name' => 'M. Mabiala',
            'admin_phone' => '123',
            'admin_password' => 'provisoire-123',
        ])
        ->call('create')
        ->assertHasFormErrors(['admin_phone']);

    expect(Tenant::count())->toBe(0);
});
