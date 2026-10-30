<?php

use App\Filament\School\Pages\EditSchoolProfile;
use App\Filament\School\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\School\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\School\Resources\SchoolYears\Pages\CreateSchoolYear;
use App\Filament\School\Resources\StaffMembers\Pages\ListStaffMembers;
use App\Models\User;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Core\Tenancy\TenantContext;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2027-03-15 10:00:00');

    [$this->school, $this->admin] = provisionSchool('lycee-a');
    [$this->otherSchool] = provisionSchool('lycee-b');

    $this->context = app(TenantContext::class);
});

afterEach(fn () => Carbon::setTestNow());

it('ouvre toutes les pages de structure pour l\'administrateur', function (string $path) {
    $this->actingAs($this->admin)
        ->get("/ecole/lycee-a/{$path}")
        ->assertOk();
})->with(['annees-scolaires', 'annees-scolaires/nouveau', 'classes', 'classes/nouveau', 'personnel', 'niveaux', 'matieres', 'series', 'cycles', 'profil']);

it('laisse un enseignant consulter les classes sans pouvoir les modifier', function () {
    $teacher = User::factory()->memberOf($this->school, 'teacher')->create();

    $this->actingAs($teacher)->get('/ecole/lycee-a/classes')->assertOk();
    $this->actingAs($teacher)->get('/ecole/lycee-a/classes/nouveau')->assertForbidden();
    $this->actingAs($teacher)->get('/ecole/lycee-a/personnel')->assertForbidden();
    $this->actingAs($teacher)->get('/ecole/lycee-a/profil')->assertNotFound();
});

it('ne liste que les classes de l\'école', function () {
    $ownClass = $this->context->run($this->school, fn () => SchoolClass::create([
        'school_year_id' => SchoolYear::query()->open()->value('id'),
        'level_id' => Level::firstWhere('code', '6E')->id,
        'name' => '6e A',
    ]));
    $foreignClass = $this->context->run($this->otherSchool, fn () => SchoolClass::create([
        'school_year_id' => SchoolYear::query()->open()->value('id'),
        'level_id' => Level::firstWhere('code', '6E')->id,
        'name' => '6e A',
    ]));

    actInSchool($this->admin, $this->school);

    Livewire::test(ListSchoolClasses::class)
        ->assertCanSeeTableRecords([$ownClass])
        ->assertCanNotSeeTableRecords([$foreignClass]);
});

it('crée une classe dans l\'année ouverte', function () {
    actInSchool($this->admin, $this->school);
    $level = Level::firstWhere('code', '2ND');

    Livewire::test(CreateSchoolClass::class)
        ->assertSchemaStateSet(['school_year_id' => SchoolYear::query()->open()->value('id')])
        ->fillForm(['level_id' => $level->id, 'name' => '2nde C1', 'capacity' => 55])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SchoolClass::firstWhere('name', '2nde C1'))
        ->tenant_id->toBe($this->school->id)
        ->level_id->toBe($level->id);
});

it('crée une année scolaire avec ses trimestres depuis le back-office', function () {
    actInSchool($this->admin, $this->school);

    Livewire::test(CreateSchoolYear::class)
        ->fillForm(['start_year' => 2027])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SchoolYear::firstWhere('name', '2027-2028')->terms()->count())->toBe(3);
});

it('signale une année déjà existante dans le formulaire', function () {
    actInSchool($this->admin, $this->school);

    Livewire::test(CreateSchoolYear::class)
        ->fillForm(['start_year' => 2026])
        ->call('create')
        ->assertHasFormErrors(['start_year']);
});

it('ouvre un accès à un membre du personnel depuis la liste', function () {
    actInSchool($this->admin, $this->school);
    $staff = StaffMember::factory()->create(['phone' => '06 777 77 77']);

    Livewire::test(ListStaffMembers::class)
        ->callAction(TestAction::make('grantAccess')->table($staff), ['role' => 'teacher'])
        ->assertNotified();

    expect($staff->fresh()->hasAccess())->toBeTrue();
});

it('enregistre le profil de l\'école', function () {
    actInSchool($this->admin, $this->school);

    Livewire::test(EditSchoolProfile::class)
        ->fillForm([
            'name' => 'Lycée Savorgnan de Brazza',
            'settings.city' => 'Brazzaville',
            'settings.phone' => '05 123 45 67',
            'settings.primary_color' => '#1d4ed8',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->school->refresh();

    expect($this->school->name)->toBe('Lycée Savorgnan de Brazza')
        ->and($this->school->settings->city)->toBe('Brazzaville')
        ->and($this->school->settings->phone)->toBe('+242051234567');
});
