<?php

use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Assessments\Actions\ManageTerm;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\Invoice;
use App\Modules\ParentPortal\Actions\GrantParentAccess;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Actions\TakeAttendance;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16 09:00', 'UTC'));
    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);
    feeGrid('6E');

    $year = SchoolYear::query()->open()->sole();
    $this->term = $year->terms()->orderBy('position')->first();
    $this->class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);

    $enroll = fn (string $firstNames, string $phone): Enrollment => app(EnrollNewStudent::class)->handle(
        newStudentData(['first_names' => $firstNames]),
        [['relationship' => 'mother', 'last_name' => 'Mabiala', 'first_names' => 'Prisca', 'phone' => $phone, 'is_financially_responsible' => true]],
        $this->class,
    );
    $this->child = $enroll('Grâce', '+242063333333');
    $this->stranger = $enroll('Intrus', '+242065550009');
    Enrollment::query()->update(['enrolled_on' => '2026-10-01']);

    $this->guardian = Guardian::firstWhere('phone', '+242063333333');
    $this->temporaryPassword = app(GrantParentAccess::class)->handle($this->guardian, $this->admin);
    $this->parent = User::firstWhere('phone', '+242063333333');
});

function asParent(): void
{
    Sanctum::actingAs(test()->parent);
    test()->withHeader('X-Tenant', test()->school->uuid);
}

it('ouvre l\'app à un responsable : compte, rôle parent et lien avec sa fiche', function () {
    expect($this->temporaryPassword)->toBeString()->toHaveLength(10)
        ->and($this->parent->must_change_password)->toBeTrue()
        ->and($this->guardian->refresh()->user_id)->toBe($this->parent->id)
        ->and($this->parent->hasRole('parent'))->toBeTrue();

    expect(fn () => app(GrantParentAccess::class)->handle($this->guardian->refresh()))
        ->toThrow(BusinessRuleViolation::class, 'déjà accès');
});

it('réutilise le compte d\'un parent déjà connu dans une autre école', function () {
    $existing = User::factory()->create(['phone' => '+242065550009']);

    expect(app(GrantParentAccess::class)->handle(Guardian::firstWhere('phone', '+242065550009')))->toBeNull()
        ->and(Guardian::firstWhere('phone', '+242065550009')->user_id)->toBe($existing->id);
});

it('ne montre au parent que ses enfants, et répond introuvable pour les autres', function () {
    asParent();
    $strangerUuid = $this->stranger->student->uuid;

    $this->getJson('/api/v1/children')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $this->child->student->uuid)
        ->assertJsonPath('data.0.enrollment.class_name', '6e A')
        ->assertJsonPath('data.0.finance.balance', 205000);

    $this->getJson("/api/v1/children/{$strangerUuid}")->assertNotFound();
    $this->getJson("/api/v1/children/{$strangerUuid}/finances")->assertNotFound();
});

it('ne montre plus rien après la fermeture de l\'accès', function () {
    app(GrantParentAccess::class)->revoke($this->guardian->refresh(), $this->admin);
    asParent();

    $this->getJson('/api/v1/children')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/children/{$this->child->student->uuid}")->assertNotFound();
});

it('n\'ouvre pas les enfants d\'une école avec l\'en-tête d\'une autre', function () {
    [$otherSchool] = provisionSchool('college-b');
    app(TenantContext::class)->set($this->school);
    User::factory()->create(); // pas d'appartenance du parent à college-b

    Sanctum::actingAs($this->parent);
    $this->withHeader('X-Tenant', $otherSchool->uuid)->getJson('/api/v1/children')->assertNotFound();
});

it('donne la scolarité, l\'échéancier et le reçu PDF d\'un paiement', function () {
    $invoice = Invoice::firstWhere('enrollment_id', $this->child->id);
    $payment = app(RecordPayment::class)->handle($invoice, 50000, PaymentMethod::MobileMoney, $this->admin, 'MP261116.0900.A1');
    asParent();

    $this->getJson("/api/v1/children/{$this->child->student->uuid}/finances")
        ->assertOk()
        ->assertJsonPath('data.0.number', $invoice->number)
        ->assertJsonPath('data.0.total', 205000)
        ->assertJsonPath('data.0.paid', 50000)
        ->assertJsonPath('data.0.balance', 155000)
        ->assertJsonPath('data.0.payments.0.uuid', $payment->uuid)
        ->assertJsonPath('data.0.payments.0.method_label', 'Mobile money (MTN, Airtel)')
        ->assertJsonPath('data.0.installments.0.status_label', 'Payé');

    $this->get("/api/v1/children/{$this->child->student->uuid}/payments/{$payment->uuid}/receipt")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('ne montre que les bulletins publiés, avec le détail et le PDF', function () {
    asParent();
    $uuid = $this->child->student->uuid;

    $this->getJson("/api/v1/children/{$uuid}/results")->assertOk()->assertJsonCount(0, 'data');

    $this->term->update(['status' => TermStatus::Locked]);
    app(ManageTerm::class)->publish($this->term->refresh(), $this->admin);

    $result = $this->getJson("/api/v1/children/{$uuid}/results")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.term', 'Premier trimestre')
        ->json('data.0.uuid');

    $this->getJson("/api/v1/children/{$uuid}/results/{$result}")
        ->assertOk()
        ->assertJsonPath('data.class_name', '6e A')
        ->assertJsonStructure(['data' => ['subjects', 'attendance' => ['label']]]);

    $this->get("/api/v1/children/{$uuid}/results/{$result}/report-card")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('montre les absences et laisse le parent déposer un justificatif, examiné par l\'école', function () {
    app(TakeAttendance::class)->handle($this->class, Carbon::parse('2026-11-13'), AttendanceSlot::firstWhere('name', 'Matin'), [$this->child->id => ['status' => 'absent']], $this->admin);
    asParent();
    $uuid = $this->child->student->uuid;

    $this->getJson("/api/v1/children/{$uuid}/attendance")
        ->assertOk()
        ->assertJsonPath('data.totals.absence_minutes', 300)
        ->assertJsonPath('data.records.0.date', '2026-11-13')
        ->assertJsonPath('data.records.0.is_justified', false);

    $this->postJson("/api/v1/children/{$uuid}/absence-justifications", ['starts_on' => '2026-11-13', 'ends_on' => '2026-11-12', 'reason' => 'illness'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_on' => 'La date de fin doit être le même jour que la date de début ou après.']);

    $this->postJson("/api/v1/children/{$uuid}/absence-justifications", ['starts_on' => '2026-11-13', 'ends_on' => '2026-11-13', 'reason' => 'illness', 'comment' => 'Fièvre'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.status_label', 'À examiner');

    // En attente : l'absence n'est pas encore justifiée.
    $this->getJson("/api/v1/children/{$uuid}/attendance")->assertJsonPath('data.records.0.is_justified', false);

    app(TenantContext::class)->set($this->school);
    app(JustifyAbsences::class)->review(AbsenceJustification::sole(), true, $this->admin);

    $this->getJson("/api/v1/children/{$uuid}/attendance")
        ->assertJsonPath('data.records.0.is_justified', true)
        ->assertJsonPath('data.justifications.0.status', JustificationStatus::Accepted->value);
});

it('oblige à changer le mot de passe provisoire', function () {
    Sanctum::actingAs($this->parent);

    $this->postJson('/api/v1/auth/password', ['current_password' => 'mauvais', 'password' => 'nouveau-mot-2026', 'password_confirmation' => 'nouveau-mot-2026'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);

    $this->postJson('/api/v1/auth/password', ['current_password' => $this->temporaryPassword, 'password' => 'nouveau-mot-2026', 'password_confirmation' => 'nouveau-mot-2026'])
        ->assertOk()
        ->assertJsonPath('user.must_change_password', false);
});

it('garde la documentation OpenAPI à jour (contrat de l\'app mobile)', function () {
    $path = sys_get_temp_dir().'/talenda-openapi.json';
    $this->artisan('scramble:export', ['--path' => $path])->assertSuccessful();

    expect(json_decode((string) file_get_contents($path), true))
        ->toEqual(json_decode((string) file_get_contents(base_path('../docs/openapi.json')), true), 'docs/openapi.json est périmé : php artisan scramble:export --path=../docs/openapi.json');
});
