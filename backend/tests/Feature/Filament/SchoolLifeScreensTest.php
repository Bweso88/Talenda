<?php

use App\Filament\School\Pages\TakeAttendancePage;
use App\Filament\School\Resources\AbsenceJustifications\Pages\ManageAbsenceJustifications;
use App\Filament\School\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Filament\School\Resources\DisciplinaryIncidents\Pages\ManageDisciplinaryIncidents;
use App\Models\User;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\TeacherIdentity;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Actions\TakeAttendance;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use App\Modules\SchoolLife\Models\DisciplinaryIncident;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16 09:00', 'UTC'));
    TeacherIdentity::flush();
    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);

    $year = SchoolYear::query()->open()->sole();
    $this->class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    $this->otherClass = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e B']);
    $this->morning = AttendanceSlot::firstWhere('name', 'Matin');
    [$this->first, $this->second] = Enrollment::factory()->count(2)->in($this->class)->create(['enrolled_on' => '2026-10-01'])->all();

    $this->teacherUser = User::factory()->memberOf($this->school, 'teacher')->create();
    $staff = StaffMember::factory()->create();
    $staff->membership()->associate($this->teacherUser->memberships()->first())->save();
    ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => Subject::firstWhere('code', 'MATH')->id, 'coefficient' => 4, 'teacher_id' => $staff->id]);
});

it('ouvre les écrans de vie scolaire pour la direction', function (string $path) {
    $this->actingAs($this->admin)->get("/ecole/lycee-a/{$path}")->assertOk();
})->with(['appel', 'absences', 'justificatifs', 'discipline', 'creneaux-d-appel']);

it('ouvre tous les écrans de vie scolaire au surveillant', function (string $path) {
    $supervisor = User::factory()->memberOf($this->school, 'supervisor')->create();

    $this->actingAs($supervisor)->get("/ecole/lycee-a/{$path}")->assertOk();
})->with(['appel', 'absences', 'justificatifs', 'discipline']);

it('ne donne à l\'enseignant que l\'appel de ses classes', function () {
    actInSchool($this->teacherUser, $this->school);

    Livewire::test(TakeAttendancePage::class)
        ->assertFormFieldExists('school_class_id', fn ($field) => array_keys($field->getOptions()) === [$this->class->id])
        // Une seule classe : elle est déjà choisie.
        ->assertSchemaStateSet(['school_class_id' => $this->class->id]);

    $this->get('/ecole/lycee-a/absences')->assertForbidden();
    $this->get('/ecole/lycee-a/discipline')->assertForbidden();
    $this->get('/ecole/lycee-a/justificatifs')->assertForbidden();
});

it('fait l\'appel depuis l\'écran et le retrouve pour le corriger', function () {
    actInSchool($this->teacherUser, $this->school);

    Livewire::test(TakeAttendancePage::class)
        ->fillForm(['school_class_id' => $this->class->id, 'attendance_slot_id' => $this->morning->id])
        ->set("data.records.{$this->first->id}.status", 'absent')
        ->set("data.records.{$this->second->id}.status", 'late')
        ->set("data.records.{$this->second->id}.late_minutes", 20)
        ->callAction('save')
        ->assertNotified('Appel enregistré : 1 absent(s), 1 retard(s).');

    expect(AttendanceRecord::firstWhere('enrollment_id', $this->second->id)->status)->toBe(AttendanceStatus::Late);

    Livewire::test(TakeAttendancePage::class)
        ->fillForm(['school_class_id' => $this->class->id, 'attendance_slot_id' => $this->morning->id])
        ->assertSet("data.records.{$this->first->id}.status", 'absent')
        ->assertSee('Appel déjà fait');
});

it('justifie une absence depuis la liste des absences', function () {
    app(TakeAttendance::class)->handle($this->class, Carbon::parse('2026-11-16'), $this->morning, [$this->first->id => ['status' => 'absent']], $this->admin);
    actInSchool($this->admin, $this->school);
    $record = AttendanceRecord::first();

    Livewire::test(ListAttendanceRecords::class)
        ->assertCanSeeTableRecords([$record])
        ->callTableAction('justify', $record, ['starts_on' => '2026-11-16', 'ends_on' => '2026-11-17', 'reason' => 'illness', 'comment' => 'Certificat médical'])
        ->assertNotified('Justificatif enregistré : 1 absence(s) justifiée(s).')
        ->filterTable('justified', false)
        ->assertCanNotSeeTableRecords([$record]);

    expect(AbsenceJustification::sole()->recorded_by)->toBe($this->admin->id);
});

it('saisit un justificatif à l\'avance, avant même l\'absence', function () {
    actInSchool($this->admin, $this->school);

    Livewire::test(ManageAbsenceJustifications::class)
        ->callAction('create', ['enrollment_id' => $this->first->id, 'starts_on' => '2026-11-16', 'ends_on' => '2026-11-20', 'reason' => 'family'])
        ->assertHasNoActionErrors()
        ->assertNotified('Justificatif enregistré : 0 absence(s) déjà relevée(s) justifiée(s).');

    expect(AbsenceJustification::sole()->ends_on->toDateString())->toBe('2026-11-20');
});

it('enregistre un incident de discipline et refuse une exclusion sans durée', function () {
    actInSchool($this->admin, $this->school);

    Livewire::test(ManageDisciplinaryIncidents::class)
        ->callAction('create', ['enrollment_id' => $this->first->id, 'occurred_on' => '2026-11-13', 'description' => 'Insolence', 'sanction' => 'suspension'])
        ->assertHasActionErrors(['suspension_days' => 'required']);

    Livewire::test(ManageDisciplinaryIncidents::class)
        ->callAction('create', ['enrollment_id' => $this->first->id, 'occurred_on' => '2026-11-13', 'description' => 'Insolence', 'sanction' => 'suspension', 'suspension_days' => 2])
        ->assertHasNoActionErrors();

    expect(DisciplinaryIncident::sole()->suspension_days)->toBe(2);
});
