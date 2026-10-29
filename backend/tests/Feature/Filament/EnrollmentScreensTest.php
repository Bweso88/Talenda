<?php

use App\Filament\School\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\School\Resources\Students\Pages\CreateStudent;
use App\Filament\School\Resources\Students\Pages\ImportStudents;
use App\Filament\School\Resources\Students\Pages\ListStudents;
use App\Models\User;
use App\Modules\Academic\Actions\CreateSchoolYear;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\EnrollmentType;
use App\Modules\Enrollments\Import\StudentImportColumns;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Enrollments\Models\Student;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2027-03-15 10:00:00');

    [$this->school, $this->admin] = provisionSchool('lycee-a');
    [$this->otherSchool] = provisionSchool('lycee-b');

    actInSchool($this->admin, $this->school);

    $this->year = SchoolYear::query()->open()->sole();
    $this->sixthA = SchoolClass::create([
        'school_year_id' => $this->year->id,
        'level_id' => Level::firstWhere('code', '6E')->id,
        'name' => '6e A',
        'capacity' => 40,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('ouvre les écrans d\'inscription pour l\'administrateur', function (string $path) {
    $this->get("/ecole/lycee-a/{$path}")->assertOk();
})->with(['eleves', 'eleves/nouvelle-inscription', 'eleves/import', 'inscriptions']);

it('réserve les inscriptions au secrétariat et à la direction', function () {
    $cashier = User::factory()->memberOf($this->school, 'cashier')->create();
    $teacher = User::factory()->memberOf($this->school, 'teacher')->create();

    $this->actingAs($cashier)->get('/ecole/lycee-a/eleves')->assertOk();
    $this->actingAs($cashier)->get('/ecole/lycee-a/eleves/nouvelle-inscription')->assertForbidden();
    $this->actingAs($cashier)->get('/ecole/lycee-a/eleves/import')->assertForbidden();
    $this->actingAs($teacher)->get('/ecole/lycee-a/eleves')->assertForbidden();
});

it('inscrit un nouvel élève et ses responsables en un seul écran', function () {
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'last_name' => 'Mabiala',
            'first_names' => 'Grâce',
            'gender' => 'F',
            'birth_date' => '2014-05-12',
            'guardians' => [
                ['relationship' => 'mother', 'last_name' => 'Okemba', 'first_names' => 'Prisca', 'phone' => '06 123 45 67', 'is_financially_responsible' => true],
            ],
            'school_class_id' => $this->sixthA->id,
            'status' => 'enrolled',
            'documents' => ['birth_certificate'],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('MABIALA Grâce est inscrit(e) — matricule 26-0001.');

    $student = Student::firstWhere('registration_number', '26-0001');

    expect($student->activeEnrollment->school_class_id)->toBe($this->sixthA->id)
        ->and($student->activeEnrollment->documents)->toBe(['birth_certificate'])
        ->and($student->guardianLinks()->sole()->is_financially_responsible)->toBeTrue()
        ->and($student->tenant_id)->toBe($this->school->id);
});

it('bloque un doublon probable et explique quoi faire', function () {
    Student::factory()->create(['last_name' => 'MABIALA', 'first_names' => 'Grâce', 'birth_date' => '2014-05-12', 'registration_number' => '26-0042']);

    Livewire::test(CreateStudent::class)
        ->fillForm([
            'last_name' => 'Mabiala',
            'first_names' => 'Grâce',
            'gender' => 'F',
            'birth_date' => '2014-05-12',
            'guardians' => [],
            'school_class_id' => $this->sixthA->id,
            'status' => 'enrolled',
        ])
        ->call('create')
        ->assertNotified();

    expect(Student::count())->toBe(1);
});

it('ne liste que les élèves de l\'école', function () {
    $own = Student::factory()->create();
    $foreign = app(TenantContext::class)->run($this->otherSchool, fn () => Student::factory()->create());

    Livewire::test(ListStudents::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$foreign]);
});

it('réinscrit une classe entière dans l\'année suivante', function () {
    $enrollments = Enrollment::factory()->count(3)->in($this->sixthA)->create();
    $nextYear = app(CreateSchoolYear::class)->handle(2027);
    $fifthA = SchoolClass::create(['school_year_id' => $nextYear->id, 'level_id' => Level::firstWhere('code', '5E')->id, 'name' => '5e A']);

    Livewire::test(ListEnrollments::class)
        ->selectTableRecords($enrollments)
        ->callAction(TestAction::make('reEnroll')->table()->bulk(), ['school_class_id' => $fifthA->id])
        ->assertNotified('3 élève(s) réinscrit(s) en 5e A.');

    expect($fifthA->enrollments()->count())->toBe(3)
        ->and($fifthA->enrollments()->pluck('type')->unique()->all())->toEqual([EnrollmentType::ReEnrollment]);
});

it('télécharge le certificat de scolarité depuis la liste', function () {
    $enrollment = Enrollment::factory()->in($this->sixthA)->create();

    Livewire::test(ListEnrollments::class)
        ->callAction(TestAction::make('schoolCertificate')->table($enrollment))
        ->assertFileDownloaded("certificat-scolarite-{$enrollment->student->registration_number}.pdf");
});

it('déclare un départ depuis la liste', function () {
    $enrollment = Enrollment::factory()->in($this->sixthA)->create(['enrolled_on' => '2026-10-02']);

    Livewire::test(ListEnrollments::class)
        ->callAction(TestAction::make('departure')->table($enrollment), [
            'status' => 'transferred_out',
            'left_on' => '2027-03-10',
            'destination_school' => 'Lycée de Dolisie',
        ]);

    expect($enrollment->fresh())
        ->status->toBe(EnrollmentStatus::TransferredOut)
        ->destination_school->toBe('Lycée de Dolisie');
});

it('analyse puis importe un fichier depuis l\'écran d\'import', function () {
    // Livewire supprime les fichiers temporaires « anciens » : avec l'horloge
    // figée en 2027, le fichier téléversé maintenant le serait aussitôt.
    Carbon::setTestNow();
    $csv = implode(';', StudentImportColumns::COLUMNS)."\nMabiala;Grâce;F;12/05/2014;Brazzaville;6e A;Okemba;Prisca;06 123 45 67;Mère;\n";

    Livewire::test(ImportStudents::class)
        ->fillForm([
            'school_year_id' => $this->year->id,
            'file' => UploadedFile::fake()->createWithContent('eleves.csv', $csv),
        ])
        ->call('analyze')
        ->assertSet('errorMessages', [])
        ->assertSet('readyCount', 1)
        ->call('import')
        ->assertRedirect();

    expect(Student::count())->toBe(1)
        ->and(Guardian::sole()->phone)->toBe('+242061234567');
});
