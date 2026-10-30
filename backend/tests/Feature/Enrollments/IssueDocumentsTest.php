<?php

use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Actions\IssueDocuments;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;

beforeEach(function () {
    [$this->tenant] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->class = SchoolClass::create([
        'school_year_id' => SchoolYear::query()->open()->value('id'),
        'level_id' => Level::firstWhere('code', '6E')->id,
        'name' => '6e A',
    ]);
});

it('délivre des certificats de scolarité numérotés et journalisés', function () {
    $enrollment = app(EnrollNewStudent::class)->handle(newStudentData(), [], $this->class);

    $first = app(IssueDocuments::class)->schoolCertificate($enrollment);
    app(IssueDocuments::class)->schoolCertificate($enrollment);

    $numbers = AuditLog::where('action', 'document.school_certificate_issued')->orderBy('id')->get()->pluck('metadata.number');
    $yearName = SchoolYear::query()->open()->value('name');

    expect($first)->toStartWith('%PDF')
        ->and($numbers->all())->toBe(["0001/{$yearName}", "0002/{$yearName}"]);
});

it('refuse un certificat pour une simple préinscription', function () {
    $enrollment = app(EnrollNewStudent::class)->handle(newStudentData(), [], $this->class, EnrollmentStatus::PreRegistered);

    app(IssueDocuments::class)->schoolCertificate($enrollment);
})->throws(BusinessRuleViolation::class);

it('imprime les cartes des seuls élèves en cours de scolarité', function () {
    $present = app(EnrollNewStudent::class)->handle(newStudentData(), [], $this->class);
    $gone = Enrollment::factory()->in($this->class)->create(['status' => EnrollmentStatus::Withdrawn]);

    expect(app(IssueDocuments::class)->studentCards(Enrollment::all()))->toStartWith('%PDF')
        ->and(AuditLog::where('action', 'document.student_cards_issued')->value('metadata'))->toBe(['count' => 1]);

    app(IssueDocuments::class)->studentCards(Enrollment::whereKey($gone->id)->get());
})->throws(BusinessRuleViolation::class, 'Aucun élève inscrit');

it('affiche les numéros congolais de façon lisible', function () {
    expect(PhoneNumber::format('+242061234567'))->toBe('+242 06 123 45 67')
        ->and(PhoneNumber::format(null))->toBeNull();
});
