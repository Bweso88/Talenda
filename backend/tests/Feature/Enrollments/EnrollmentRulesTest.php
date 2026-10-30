<?php

use App\Modules\Academic\Actions\CreateSchoolYear;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Actions\ManageEnrollment;
use App\Modules\Enrollments\Actions\ReEnrollStudent;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\EnrollmentType;
use App\Modules\Enrollments\Enums\YearEndDecision;
use App\Modules\Enrollments\Events\StudentEnrolled;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Carbon::setTestNow('2027-03-15 10:00:00');

    [$this->tenant] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->year = SchoolYear::query()->open()->sole();
    $this->sixthA = SchoolClass::create([
        'school_year_id' => $this->year->id,
        'level_id' => Level::firstWhere('code', '6E')->id,
        'name' => '6e A',
        'capacity' => 2,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function enrollNew(SchoolClass $class, array $student = [], array $guardians = []): Enrollment
{
    return app(EnrollNewStudent::class)->handle(newStudentData($student), $guardians, $class);
}

it('inscrit un nouvel élève avec un matricule séquentiel', function () {
    Event::fake([StudentEnrolled::class]);

    $first = enrollNew($this->sixthA);
    $second = enrollNew($this->sixthA, ['first_names' => 'Arsène', 'gender' => 'M']);

    expect($first->student->registration_number)->toBe('26-0001')
        ->and($second->student->registration_number)->toBe('26-0002')
        ->and($first->student->last_name)->toBe('MABIALA')
        ->and($first->type)->toBe(EnrollmentType::New)
        ->and($first->status)->toBe(EnrollmentStatus::Enrolled)
        ->and(AuditLog::where('action', 'enrollment.created')->count())->toBe(2);

    Event::assertDispatchedTimes(StudentEnrolled::class, 2);
});

it('réutilise le responsable d\'une fratrie grâce à son téléphone', function () {
    $mother = ['last_name' => 'Okemba', 'first_names' => 'Prisca', 'phone' => '06 123 45 67', 'relationship' => 'mother'];

    $first = enrollNew($this->sixthA, guardians: [$mother]);
    $second = enrollNew($this->sixthA, ['first_names' => 'Arsène', 'gender' => 'M'], [$mother]);

    expect(Guardian::count())->toBe(1)
        ->and(Guardian::sole()->phone)->toBe('+242061234567')
        ->and(Guardian::sole()->students()->count())->toBe(2)
        ->and($first->student->guardianLinks()->sole()->is_primary_contact)->toBeTrue()
        ->and($second->student->guardians()->sole()->pivot->relationship->value)->toBe('mother');
});

it('signale un élève probablement déjà connu', function () {
    enrollNew($this->sixthA);

    enrollNew($this->sixthA, ['last_name' => 'mabiala', 'first_names' => 'GRÂCE DIVINE']);
})->throws(BusinessRuleViolation::class, 'existe déjà (matricule 26-0001)');

it('refuse une inscription dans une classe complète', function () {
    enrollNew($this->sixthA, ['first_names' => 'Un']);
    enrollNew($this->sixthA, ['first_names' => 'Deux']);

    enrollNew($this->sixthA, ['first_names' => 'Trois']);
})->throws(BusinessRuleViolation::class, 'La classe 6e A est complète (2 places).');

it('libère la place d\'un élève parti', function () {
    $leaving = enrollNew($this->sixthA, ['first_names' => 'Un']);
    enrollNew($this->sixthA, ['first_names' => 'Deux']);

    app(ManageEnrollment::class)->recordDeparture($leaving, EnrollmentStatus::TransferredOut, now(), 'Déménagement', 'Lycée de Dolisie');

    enrollNew($this->sixthA, ['first_names' => 'Trois']);

    expect($this->sixthA->occupiedSeats())->toBe(2)
        ->and($leaving->fresh()->destination_school)->toBe('Lycée de Dolisie');
});

it('garantit en base une seule inscription en cours par élève et par année', function () {
    $enrollment = enrollNew($this->sixthA);
    $sixthB = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => $this->sixthA->level_id, 'name' => '6e B']);

    Enrollment::create([
        'student_id' => $enrollment->student_id,
        'school_year_id' => $this->year->id,
        'school_class_id' => $sixthB->id,
        'type' => EnrollmentType::New,
        'status' => EnrollmentStatus::Enrolled,
        'enrolled_on' => now(),
    ]);
})->throws(QueryException::class);

it('garantit en base que la classe appartient à l\'année de l\'inscription', function () {
    $student = Student::factory()->create();
    $nextYear = app(CreateSchoolYear::class)->handle(2027);

    Enrollment::create([
        'student_id' => $student->id,
        'school_year_id' => $nextYear->id,
        'school_class_id' => $this->sixthA->id,
        'type' => EnrollmentType::New,
        'status' => EnrollmentStatus::Enrolled,
        'enrolled_on' => now(),
    ]);
})->throws(QueryException::class);

it('change un élève de classe dans la même année', function () {
    $enrollment = enrollNew($this->sixthA);
    $sixthB = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => $this->sixthA->level_id, 'name' => '6e B']);

    app(ManageEnrollment::class)->changeClass($enrollment, $sixthB);

    expect($enrollment->fresh()->school_class_id)->toBe($sixthB->id);
});

it('confirme une préinscription', function () {
    $enrollment = app(EnrollNewStudent::class)->handle(newStudentData(), [], $this->sixthA, EnrollmentStatus::PreRegistered);

    app(ManageEnrollment::class)->confirm($enrollment);

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Enrolled);
});

describe('réinscription', function () {
    beforeEach(function () {
        $this->previous = enrollNew($this->sixthA);
        $this->nextYear = app(CreateSchoolYear::class)->handle(2027);
        $this->fifthA = SchoolClass::create(['school_year_id' => $this->nextYear->id, 'level_id' => Level::firstWhere('code', '5E')->id, 'name' => '5e A']);
    });

    it('réinscrit l\'élève dans une classe de l\'année suivante', function () {
        $enrollment = app(ReEnrollStudent::class)->handle($this->previous->student, $this->fifthA);

        expect($enrollment->type)->toBe(EnrollmentType::ReEnrollment)
            ->and($this->previous->student->enrollments()->count())->toBe(2)
            ->and($enrollment->student->registration_number)->toBe('26-0001');
    });

    it('propose le niveau suivant, ou le même en cas de redoublement', function () {
        expect(ReEnrollStudent::suggestedLevel($this->previous)->code)->toBe('5E');

        $this->previous->update(['decision' => YearEndDecision::Repeat]);

        expect(ReEnrollStudent::suggestedLevel($this->previous->fresh())->code)->toBe('6E');
    });

    it('refuse une double inscription la même année', function () {
        app(ReEnrollStudent::class)->handle($this->previous->student, $this->fifthA);

        app(ReEnrollStudent::class)->handle($this->previous->student, $this->fifthA);
    })->throws(BusinessRuleViolation::class, 'est déjà inscrit(e) en 5e A');

    it('refuse de réinscrire un élève exclu', function () {
        $this->previous->update(['decision' => YearEndDecision::Excluded]);

        app(ReEnrollStudent::class)->handle($this->previous->student, $this->fifthA);
    })->throws(BusinessRuleViolation::class, 'exclu');
});

it('refuse toute inscription dans une année clôturée', function () {
    $this->year->update(['status' => 'closed']);

    enrollNew($this->sixthA->fresh());
})->throws(BusinessRuleViolation::class, 'est clôturée');
