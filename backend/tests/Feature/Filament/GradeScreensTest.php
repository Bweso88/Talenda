<?php

use App\Filament\School\Pages\ClassCouncil;
use App\Filament\School\Pages\GradingSettingsPage;
use App\Filament\School\Pages\SubjectComments;
use App\Filament\School\Pages\YearEndDecisionsPage;
use App\Filament\School\Resources\Assessments\Pages\CreateAssessment;
use App\Filament\School\Resources\Assessments\Pages\EnterScores;
use App\Filament\School\Resources\Assessments\Pages\ListAssessments;
use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\TeacherIdentity;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\AssessmentType;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Assessments\Models\TermComment;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\YearEndDecision;
use App\Modules\Enrollments\Models\Enrollment;
use Livewire\Livewire;

beforeEach(function () {
    TeacherIdentity::flush();
    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);

    $this->year = SchoolYear::query()->open()->sole();
    $this->term = $this->year->terms()->first();
    $this->class = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);

    $this->teacherUser = User::factory()->memberOf($this->school, 'teacher')->create();
    $staff = StaffMember::factory()->create();
    $staff->membership()->associate($this->teacherUser->memberships()->first())->save();

    $this->math = ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => Subject::firstWhere('code', 'MATH')->id, 'coefficient' => 4, 'teacher_id' => $staff->id]);
    $this->french = ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => Subject::firstWhere('code', 'FR')->id, 'coefficient' => 2]);
    $this->enrollments = Enrollment::factory()->count(2)->in($this->class)->create();

    $this->mathTest = Assessment::create(['class_subject_id' => $this->math->id, 'term_id' => $this->term->id, 'assessment_type_id' => AssessmentType::firstWhere('code', 'DEVOIR')->id, 'title' => 'Devoir 1', 'held_on' => $this->term->starts_on, 'max_score' => 20]);
    $this->frenchTest = Assessment::create(['class_subject_id' => $this->french->id, 'term_id' => $this->term->id, 'assessment_type_id' => AssessmentType::firstWhere('code', 'DEVOIR')->id, 'title' => 'Dictée', 'held_on' => $this->term->starts_on, 'max_score' => 20]);
});

it('ouvre les écrans de notes pour la direction', function (string $path) {
    $this->actingAs($this->admin)->get("/ecole/lycee-a/{$path}")->assertOk();
})->with(['evaluations', 'evaluations/nouvelle', 'appreciations', 'conseil-de-classe', 'decisions-de-fin-d-annee', 'notation', 'types-d-evaluation']);

it('ne montre à l\'enseignant que les évaluations de ses matières', function () {
    actInSchool($this->teacherUser, $this->school);

    Livewire::test(ListAssessments::class)
        ->assertCanSeeTableRecords([$this->mathTest])
        ->assertCanNotSeeTableRecords([$this->frenchTest]);

    // Introuvable plutôt qu'interdit : l'enseignant ne sait même pas qu'elle existe.
    $this->get("/ecole/lycee-a/evaluations/{$this->frenchTest->id}/notes")->assertNotFound();
    $this->get('/ecole/lycee-a/conseil-de-classe')->assertForbidden();
    $this->get('/ecole/lycee-a/notation')->assertForbidden();
});

it('refuse à l\'enseignant de créer une évaluation dans une matière qui n\'est pas la sienne', function () {
    actInSchool($this->teacherUser, $this->school);

    Livewire::test(CreateAssessment::class)
        ->fillForm(['class_subject_id' => $this->french->id, 'term_id' => $this->term->id, 'assessment_type_id' => AssessmentType::first()->id, 'title' => 'Intrus', 'held_on' => '2026-11-10', 'max_score' => 20])
        ->call('create')
        ->assertHasFormErrors(['class_subject_id']);

    expect(Assessment::where('title', 'Intrus')->exists())->toBeFalse();
});

it('saisit les notes de toute la classe dans la grille', function () {
    actInSchool($this->teacherUser, $this->school);
    [$first, $second] = $this->enrollments->all();

    Livewire::test(EnterScores::class, ['record' => $this->mathTest->id])
        ->fillForm(["scores.{$first->id}.value" => '14.5', "scores.{$second->id}.status" => 'absent'])
        ->callAction('save')
        ->assertNotified('2 note(s) enregistrée(s).');

    expect($this->mathTest->scores()->where('enrollment_id', $first->id)->value('value'))->toBe('14.50');
});

it('affiche la grille en lecture seule une fois la période verrouillée', function () {
    $this->term->update(['status' => TermStatus::Locked]);
    actInSchool($this->teacherUser, $this->school);

    Livewire::test(EnterScores::class, ['record' => $this->mathTest->id])
        ->assertActionHidden('save');
});

it('enregistre les appréciations de l\'enseignant', function () {
    actInSchool($this->teacherUser, $this->school);
    $first = $this->enrollments->first();

    Livewire::test(SubjectComments::class)
        ->fillForm(['class_subject_id' => $this->math->id, 'term_id' => $this->term->id])
        ->set("data.comments.{$first->id}", 'Bon trimestre.')
        ->callAction('save')
        ->assertNotified('Appréciations enregistrées.');

    expect(TermComment::where('class_subject_id', $this->math->id)->value('body'))->toBe('Bon trimestre.');
});

it('verrouille, publie et télécharge les bulletins depuis le conseil de classe', function () {
    actInSchool($this->admin, $this->school);

    $page = Livewire::test(ClassCouncil::class)
        ->fillForm(['term_id' => $this->term->id, 'school_class_id' => $this->class->id])
        ->callAction('lock')
        ->assertNotified('Saisie verrouillée.')
        ->callAction('publish')
        ->assertNotified('Bulletins publiés.');

    expect($this->term->fresh()->status)->toBe(TermStatus::Published)
        ->and(TermResult::count())->toBe(2);

    $page->callAction('reportCards')->assertFileDownloaded('bulletins-6e-a-premier-trimestre.pdf');
});

it('enregistre les décisions de fin d\'année', function () {
    actInSchool($this->admin, $this->school);
    $first = $this->enrollments->first();

    Livewire::test(YearEndDecisionsPage::class)
        ->fillForm(['school_class_id' => $this->class->id])
        ->set("data.decisions.{$first->id}", 'repeat')
        ->callAction('save')
        ->assertNotified('Décisions enregistrées.');

    expect($first->fresh()->decision)->toBe(YearEndDecision::Repeat);
});

it('modifie les règles de notation', function () {
    actInSchool($this->admin, $this->school);

    Livewire::test(GradingSettingsPage::class)
        ->fillForm(['passing_average' => 10, 'absence_policy' => 'zero', 'distinctions' => [['name' => 'Tableau d\'honneur', 'min' => 12], ['name' => 'Félicitations', 'min' => 16]]])
        ->callAction('save')
        ->assertNotified('Règles de notation enregistrées.');

    expect(GradingSetting::current()->distinctionFor(16.5))->toBe('Félicitations')
        ->and(GradingSetting::current()->absence_policy->value)->toBe('zero');
});
