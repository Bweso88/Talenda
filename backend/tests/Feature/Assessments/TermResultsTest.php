<?php

use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Assessments\Actions\IssueReportCards;
use App\Modules\Assessments\Actions\ManageTerm;
use App\Modules\Assessments\Actions\SaveScores;
use App\Modules\Assessments\Actions\SaveTermComments;
use App\Modules\Assessments\Actions\YearEndDecisions;
use App\Modules\Assessments\Models\AnnualResult;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\AssessmentType;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Assessments\Models\Score;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Assessments\Support\ComputeClassTermResults;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\YearEndDecision;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\QueryException;

beforeEach(function () {
    [$this->tenant, $this->admin] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->year = SchoolYear::query()->open()->sole();
    $this->term = $this->year->terms()->first();
    $this->class = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    $this->teacher = StaffMember::factory()->create(['last_name' => 'Okemba', 'first_name' => 'Prisca']);
    $this->math = ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => Subject::firstWhere('code', 'MATH')->id, 'coefficient' => 4, 'teacher_id' => $this->teacher->id]);
    $this->french = ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => Subject::firstWhere('code', 'FR')->id, 'coefficient' => 2]);

    $this->students = collect(['Ange', 'Bertin', 'Clarisse'])->map(fn (string $name) => Enrollment::factory()->in($this->class)->create([
        'student_id' => Student::factory()->create(['last_name' => 'ELEVE', 'first_names' => $name])->id,
    ]));
    [$this->ange, $this->bertin, $this->clarisse] = $this->students->all();

    $this->devoir = AssessmentType::firstWhere('code', 'DEVOIR');
    $this->composition = AssessmentType::firstWhere('code', 'COMPOSITION');
});

function assessment(ClassSubject $classSubject, AssessmentType $type, array $scores, float $max = 20): Assessment
{
    $assessment = Assessment::create([
        'class_subject_id' => $classSubject->id,
        'term_id' => test()->term->id,
        'assessment_type_id' => $type->id,
        'title' => $type->name,
        'held_on' => test()->term->starts_on,
        'max_score' => $max,
    ]);

    app(SaveScores::class)->handle($assessment, $scores);

    return $assessment;
}

it('calcule moyennes, coefficients, rangs et statistiques de la classe', function () {
    // Maths (coef 4) : devoir /10 ramené sur 20, composition /20.
    assessment($this->math, $this->devoir, [$this->ange->id => ['value' => 8], $this->bertin->id => ['value' => 5], $this->clarisse->id => ['value' => 6]], max: 10);
    assessment($this->math, $this->composition, [$this->ange->id => ['value' => 14], $this->bertin->id => ['value' => 12], $this->clarisse->id => ['value' => 10]]);
    // Français (coef 2).
    assessment($this->french, $this->composition, [$this->ange->id => ['value' => '12,5'], $this->bertin->id => ['value' => 14], $this->clarisse->id => ['status' => 'absent']]);

    $results = app(ComputeClassTermResults::class)->handle($this->term, $this->class);

    // Ange : maths (16 + 14) / 2 = 15 ; français 12,5 ; générale (15×4 + 12,5×2) / 6 = 14,17.
    expect($results->subjectAverage($this->ange->id, $this->math->id))->toBe(15.0)
        ->and($results->average($this->ange->id))->toBe(14.17)
        // Bertin : maths (10 + 12) / 2 = 11 ; français 14 ; (44 + 28) / 6 = 12.
        ->and($results->average($this->bertin->id))->toBe(12.0)
        // Clarisse absente en français (non compté) : maths (12 + 10) / 2 = 11.
        ->and($results->subjectAverage($this->clarisse->id, $this->french->id))->toBeNull()
        ->and($results->average($this->clarisse->id))->toBe(11.0)
        ->and($results->generals[$this->clarisse->id]['coefficient'])->toBe(4.0)
        ->and($results->rank($this->ange->id))->toBe(['rank' => 1, 'tied' => false])
        ->and($results->rank($this->clarisse->id))->toBe(['rank' => 3, 'tied' => false])
        ->and($results->classStats['highest'])->toBe(14.17)
        ->and($results->subjectStats[$this->math->id]['lowest'])->toBe(11.0);
});

it('compte 0 pour une absence si l\'école le décide', function () {
    GradingSetting::current()->update(['absence_policy' => 'zero']);
    assessment($this->french, $this->composition, [$this->clarisse->id => ['status' => 'absent']]);

    expect(app(ComputeClassTermResults::class)->handle($this->term, $this->class)->subjectAverage($this->clarisse->id, $this->french->id))->toBe(0.0);
});

it('ne classe pas un élève parti en cours de période', function () {
    $this->bertin->update(['status' => EnrollmentStatus::Withdrawn, 'left_on' => $this->term->starts_on->copy()->addWeek()]);

    expect(app(ComputeClassTermResults::class)->handle($this->term, $this->class)->classSize())->toBe(2);
});

it('refuse une note hors barème', function () {
    assessment($this->math, $this->devoir, [$this->ange->id => ['value' => 11]], max: 10);
})->throws(BusinessRuleViolation::class, 'invalide (0 à 10)');

it('refuse la saisie quand la période est verrouillée', function () {
    $assessment = assessment($this->math, $this->devoir, []);
    app(ManageTerm::class)->lock($this->term, $this->admin);

    app(SaveScores::class)->handle($assessment->fresh(), [$this->ange->id => ['value' => 10]]);
})->throws(BusinessRuleViolation::class, 'saisie est fermée');

it('efface une note quand la case est vidée', function () {
    $assessment = assessment($this->math, $this->devoir, [$this->ange->id => ['value' => 10]]);

    app(SaveScores::class)->handle($assessment, [$this->ange->id => ['value' => '']]);

    expect($assessment->scores()->count())->toBe(0);
});

describe('publication', function () {
    beforeEach(function () {
        assessment($this->math, $this->composition, [$this->ange->id => ['value' => 15], $this->bertin->id => ['value' => 9], $this->clarisse->id => ['value' => 12]]);
        assessment($this->french, $this->composition, [$this->ange->id => ['value' => 13], $this->bertin->id => ['value' => 9], $this->clarisse->id => ['value' => 12]]);
        app(SaveTermComments::class)->handle($this->term, null, [$this->ange->id => 'Excellent trimestre.'], $this->admin);
        app(SaveTermComments::class)->handle($this->term, $this->math, [$this->ange->id => 'Très bon travail.'], $this->admin);
    });

    it('exige le verrouillage avant la publication', function () {
        app(ManageTerm::class)->publish($this->term, $this->admin);
    })->throws(BusinessRuleViolation::class, 'Verrouillez');

    it('fige les résultats et les appréciations à la publication', function () {
        app(ManageTerm::class)->lock($this->term, $this->admin);
        $count = app(ManageTerm::class)->publish($this->term->fresh(), $this->admin);

        $ange = TermResult::with('subjectResults')->firstWhere('enrollment_id', $this->ange->id);

        expect($count)->toBe(3)
            ->and($this->term->fresh()->status)->toBe(TermStatus::Published)
            ->and($ange->average)->toBe('14.33')
            ->and($ange->distinction)->toBe('Encouragements')
            ->and($ange->rankLabel())->toBe('1er / 3')
            ->and($ange->council_comment)->toBe('Excellent trimestre.')
            ->and($ange->subjectResults->first()->subject_name)->toBe('Mathématiques')
            ->and($ange->subjectResults->first()->teacher_name)->toBe('OKEMBA Prisca')
            ->and($ange->subjectResults->first()->comment)->toBe('Très bon travail.')
            ->and(AnnualResult::firstWhere('enrollment_id', $this->ange->id)->average)->toBe('14.33');
    });

    it('ne change pas un bulletin publié si les données changent ensuite', function () {
        app(ManageTerm::class)->lock($this->term, $this->admin);
        app(ManageTerm::class)->publish($this->term->fresh(), $this->admin);

        $this->math->update(['coefficient' => 1]);
        $this->math->subject->update(['name' => 'Maths renommées']);

        $ange = TermResult::with('subjectResults')->firstWhere('enrollment_id', $this->ange->id);

        expect($ange->average)->toBe('14.33')
            ->and($ange->subjectResults->first()->subject_name)->toBe('Mathématiques');
    });

    it('rouvre une période publiée en retirant les bulletins, avec un motif', function () {
        app(ManageTerm::class)->lock($this->term, $this->admin);
        app(ManageTerm::class)->publish($this->term->fresh(), $this->admin);

        app(ManageTerm::class)->reopen($this->term->fresh(), $this->admin, 'Erreur de note en français');

        expect($this->term->fresh()->status)->toBe(TermStatus::Locked)
            ->and(TermResult::count())->toBe(0)
            ->and(AnnualResult::count())->toBe(0);
    });

    it('produit les bulletins PDF de la classe', function () {
        app(ManageTerm::class)->lock($this->term, $this->admin);
        app(ManageTerm::class)->publish($this->term->fresh(), $this->admin);

        expect(app(IssueReportCards::class)->handle($this->term->fresh(), $this->class))->toStartWith('%PDF');
    });

    it('propose les décisions de fin d\'année selon la moyenne annuelle', function () {
        app(ManageTerm::class)->lock($this->term, $this->admin);
        app(ManageTerm::class)->publish($this->term->fresh(), $this->admin);
        $settings = GradingSetting::current();

        $propose = fn (Enrollment $enrollment) => YearEndDecisions::propose(
            $enrollment->fresh()->load('schoolClass.level'),
            AnnualResult::firstWhere('enrollment_id', $enrollment->id),
            $settings,
        );

        expect($propose($this->ange))->toBe(YearEndDecision::Promoted)
            ->and($propose($this->bertin))->toBe(YearEndDecision::Repeat);

        app(YearEndDecisions::class)->save($this->class, [$this->bertin->id => 'promoted'], $this->admin);

        expect($this->bertin->fresh()->decision)->toBe(YearEndDecision::Promoted);
    });
});

it('garantit en base qu\'une note « présente » a une valeur', function () {
    $assessment = assessment($this->math, $this->devoir, []);

    Score::create(['assessment_id' => $assessment->id, 'enrollment_id' => $this->ange->id, 'status' => 'present', 'value' => null]);
})->throws(QueryException::class);

it('limite un enseignant à ses propres matières', function () {
    $user = User::factory()->memberOf($this->tenant, 'teacher')->create();
    $this->teacher->membership()->associate($user->memberships()->first())->save();
    $maths = assessment($this->math, $this->devoir, []);
    $french = assessment($this->french, $this->devoir, []);

    expect($user->can('update', $maths))->toBeTrue()
        ->and($user->can('update', $french))->toBeFalse();
});
