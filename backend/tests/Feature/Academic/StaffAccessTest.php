<?php

use App\Models\User;
use App\Modules\Academic\Actions\GrantStaffAccess;
use App\Modules\Academic\Enums\StaffPosition;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    [$this->tenant] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);
});

it('normalise le téléphone du personnel', function () {
    $staff = StaffMember::factory()->create(['phone' => '06 555 55 55']);

    expect($staff->phone)->toBe('+242065555555');
});

it('crée un compte avec un mot de passe provisoire et le rôle de la fonction', function () {
    $staff = StaffMember::factory()->create(['phone' => '06 555 55 55', 'position' => StaffPosition::Cashier]);

    $password = app(GrantStaffAccess::class)->handle($staff, $staff->position->defaultRole());

    $user = User::firstWhere('phone', '+242065555555');

    expect($password)->toBeString()
        ->and(Hash::check($password, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->hasRole('cashier'))->toBeTrue()
        ->and($staff->fresh()->membership->user_id)->toBe($user->id)
        ->and(AuditLog::where('action', 'staff.access_granted')->exists())->toBeTrue();
});

it('réutilise le compte d\'un parent sans changer son mot de passe', function () {
    $otherSchool = Tenant::factory()->create();
    $parent = User::factory()->memberOf($otherSchool, 'parent')->create(['phone' => '+242065555555', 'password' => 'mot-de-passe-parent']);
    $staff = StaffMember::factory()->create(['phone' => '065555555']);

    $password = app(GrantStaffAccess::class)->handle($staff, 'teacher');

    expect($password)->toBeNull()
        ->and(User::count())->toBe(2)
        ->and(Hash::check('mot-de-passe-parent', $parent->fresh()->password))->toBeTrue()
        ->and($parent->hasRole('teacher'))->toBeTrue()
        ->and($parent->accessibleTenants()->pluck('id')->all())->toEqualCanonicalizing([$otherSchool->id, $this->tenant->id])
        ->and(app(TenantContext::class)->run($otherSchool, fn () => $parent->roles()->pluck('name')->all()))->toBe(['parent']);
});

it('exige un téléphone ou un e-mail', function () {
    $staff = StaffMember::factory()->create(['phone' => null, 'email' => null]);

    app(GrantStaffAccess::class)->handle($staff, 'teacher');
})->throws(BusinessRuleViolation::class);

it('refuse d\'ouvrir deux fois un accès', function () {
    $staff = StaffMember::factory()->create();
    app(GrantStaffAccess::class)->handle($staff, 'teacher');

    app(GrantStaffAccess::class)->handle($staff->fresh(), 'teacher');
})->throws(BusinessRuleViolation::class);

it('refuse le rôle parent pour le personnel', function () {
    app(GrantStaffAccess::class)->handle(StaffMember::factory()->create(), 'parent');
})->throws(BusinessRuleViolation::class);
