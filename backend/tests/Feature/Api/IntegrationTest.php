<?php

use App\Filament\School\Resources\IntegrationClients\Pages\ManageIntegrationClients as ManageIntegrationClientsPage;
use App\Models\User;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Integrations\Actions\ManageIntegrationClients;
use App\Modules\Integrations\Models\IntegrationClient;
use App\Modules\ParentPortal\Actions\GrantParentAccess;
use Livewire\Livewire;

beforeEach(function () {
    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);

    $year = SchoolYear::query()->open()->sole();
    $this->class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    $this->teacher = StaffMember::factory()->create(['first_name' => 'Prisca', 'last_name' => 'Okemba']);
    ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => Subject::firstWhere('code', 'MATH')->id, 'coefficient' => 4, 'teacher_id' => $this->teacher->id]);
    $this->enrollment = app(EnrollNewStudent::class)->handle(
        newStudentData(),
        [['relationship' => 'mother', 'last_name' => 'Mabiala', 'first_names' => 'Prisca', 'phone' => '+242063333333', 'is_primary_contact' => true]],
        $this->class,
    );
    $this->guardian = Guardian::sole();
    $temporaryPassword = app(GrantParentAccess::class)->handle($this->guardian);
    $this->parent = User::firstWhere('phone', '+242063333333');
    $this->parent->update(['password' => 'mot-de-passe-parent']);

    [, $this->token] = app(ManageIntegrationClients::class)->create('Scolia', $this->admin);
    app(TenantContext::class)->set(null);
});

it('refuse une clé absente, inconnue ou révoquée', function () {
    $this->getJson('/api/integration/v1/snapshot')->assertUnauthorized();
    $this->withToken('tlnd_inconnue')->getJson('/api/integration/v1/snapshot')->assertUnauthorized();

    app(TenantContext::class)->run($this->school, fn () => app(ManageIntegrationClients::class)->revoke(IntegrationClient::sole(), $this->admin));

    $this->withToken($this->token)->getJson('/api/integration/v1/snapshot')->assertUnauthorized();
});

it('donne la photographie de l\'école de la clé, et seulement elle', function () {
    [$other] = provisionSchool('college-b');
    app(TenantContext::class)->run($other, fn () => Guardian::factory()->create(['last_name' => 'INTRUS']));
    app(TenantContext::class)->set(null);

    $this->withToken($this->token)->getJson('/api/integration/v1/snapshot')
        ->assertOk()
        ->assertJsonPath('school.uuid', $this->school->uuid)
        ->assertJsonPath('classes.0.name', '6e A')
        ->assertJsonPath('classes.0.level_code', '6E')
        ->assertJsonPath('teaching.0.class_uuid', $this->class->uuid)
        ->assertJsonPath('teaching.0.teacher_uuid', $this->teacher->uuid)
        ->assertJsonPath('students.0.uuid', $this->enrollment->student->uuid)
        ->assertJsonPath('students.0.class_uuid', $this->class->uuid)
        ->assertJsonCount(1, 'guardians')
        ->assertJsonPath('guardians.0.phone', '+242063333333')
        ->assertJsonPath('guardians.0.account_uuid', $this->parent->uuid)
        ->assertJsonPath('guardians.0.children.0.student_uuid', $this->enrollment->student->uuid)
        ->assertJsonPath('guardians.0.children.0.relationship', 'mother')
        ->assertJsonPath('guardians.0.children.0.is_primary_contact', true);

    expect(IntegrationClient::query()->withoutGlobalScopes()->sole()->last_used_at)->not->toBeNull();
});

it('vérifie les identifiants Talenda d\'un parent de l\'école', function () {
    $this->withToken($this->token)
        ->postJson('/api/integration/v1/credentials/check', ['login' => '06 333 33 33', 'password' => 'mot-de-passe-parent'])
        ->assertOk()
        ->assertJsonPath('account_uuid', $this->parent->uuid)
        ->assertJsonPath('roles', ['parent']);

    $this->withToken($this->token)
        ->postJson('/api/integration/v1/credentials/check', ['login' => '06 333 33 33', 'password' => 'mauvais'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['login' => 'Identifiants incorrects.']);
});

it('refuse un compte qui n\'appartient pas à l\'école de la clé', function () {
    [$other] = provisionSchool('college-b');
    User::factory()->memberOf($other, 'parent')->create(['phone' => '+242065550099', 'password' => 'autre-parent-123']);
    app(TenantContext::class)->set(null);

    $this->withToken($this->token)
        ->postJson('/api/integration/v1/credentials/check', ['login' => '06 555 00 99', 'password' => 'autre-parent-123'])
        ->assertUnprocessable();
});

it('connecte Scolia depuis le back-office et affiche la clé une seule fois', function () {
    app(TenantContext::class)->set($this->school);
    app(ManageIntegrationClients::class)->revoke(IntegrationClient::sole(), $this->admin);
    actInSchool($this->admin, $this->school);

    Livewire::test(ManageIntegrationClientsPage::class)
        ->callAction('connect', ['name' => 'Scolia'])
        ->assertNotified('Scolia est connecté');

    expect(IntegrationClient::query()->whereNull('revoked_at')->count())->toBe(1)
        ->and(IntegrationClient::query()->whereNull('revoked_at')->sole()->token_prefix)->toStartWith('tlnd_');

    Livewire::test(ManageIntegrationClientsPage::class)
        ->callAction('connect', ['name' => 'Scolia'])
        ->assertNotified('Scolia est déjà connecté : révoquez l\'ancienne clé avant d\'en créer une nouvelle.');
});
