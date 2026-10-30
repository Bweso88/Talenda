<?php

use App\Filament\School\Resources\AbsenceJustifications\Pages\ManageAbsenceJustifications;
use App\Filament\School\Resources\Students\Pages\EditStudent;
use App\Filament\School\Resources\Students\RelationManagers\GuardianLinksRelationManager;
use App\Models\User;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\StudentGuardian;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16 09:00', 'UTC'));
    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);

    $year = SchoolYear::query()->open()->sole();
    $class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    $this->enrollment = app(EnrollNewStudent::class)->handle(
        newStudentData(),
        [['relationship' => 'father', 'last_name' => 'Mabiala', 'first_names' => 'Paul', 'phone' => '+242063333333']],
        $class,
    );
    $this->link = StudentGuardian::sole();
    actInSchool($this->admin, $this->school);
});

it('ouvre l\'app parents à un responsable depuis la fiche élève et affiche le mot de passe provisoire', function () {
    Livewire::test(GuardianLinksRelationManager::class, ['ownerRecord' => $this->enrollment->student, 'pageClass' => EditStudent::class])
        ->callTableAction('grantAppAccess', $this->link)
        ->assertNotified();

    $parent = User::firstWhere('phone', '+242063333333');

    expect($parent)->not->toBeNull()
        ->and($this->link->guardian->refresh()->user_id)->toBe($parent->id);

    Livewire::test(GuardianLinksRelationManager::class, ['ownerRecord' => $this->enrollment->student, 'pageClass' => EditStudent::class])
        ->assertTableActionHidden('grantAppAccess', $this->link)
        ->callTableAction('revokeAppAccess', $this->link);

    expect($this->link->guardian->refresh()->user_id)->toBeNull();
});

it('accepte un justificatif déposé par un parent', function () {
    $parent = User::factory()->memberOf($this->school, 'parent')->create();
    app(TenantContext::class)->set($this->school);
    app(JustifyAbsences::class)->submitByGuardian($this->enrollment, Carbon::parse('2026-11-13'), Carbon::parse('2026-11-13'), JustificationReason::Illness, 'Fièvre', $parent);
    $justification = AbsenceJustification::sole();

    expect(ManageAbsenceJustifications::getResource()::getNavigationBadge())->toBe('1');

    Livewire::test(ManageAbsenceJustifications::class)
        ->callTableAction('accept', $justification)
        ->assertNotified('Justificatif accepté.');

    expect($justification->refresh())
        ->status->toBe(JustificationStatus::Accepted)
        ->reviewed_by->toBe($this->admin->id);
});
