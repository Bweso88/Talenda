<?php

use App\Modules\Academic\Actions\ChangeSchoolYearStatus;
use App\Modules\Academic\Actions\CreateSchoolYear;
use App\Modules\Academic\Actions\PrepareNextSchoolYear;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2027-03-15 10:00:00');

    [$this->tenant] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->currentYear = SchoolYear::query()->open()->sole();
});

afterEach(fn () => Carbon::setTestNow());

it('refuse de créer deux fois la même année', function () {
    app(CreateSchoolYear::class)->handle(2026);
})->throws(BusinessRuleViolation::class, "L'année scolaire 2026-2027 existe déjà.");

it('refuse d\'ouvrir une deuxième année', function () {
    $nextYear = app(CreateSchoolYear::class)->handle(2027);

    app(ChangeSchoolYearStatus::class)->open($nextYear);
})->throws(BusinessRuleViolation::class, "Clôturez d'abord l'année 2026-2027");

it('garantit en base une seule année ouverte par école', function () {
    $nextYear = app(CreateSchoolYear::class)->handle(2027);

    $nextYear->update(['status' => SchoolYearStatus::Open]);
})->throws(QueryException::class);

it('clôture l\'année puis permet d\'ouvrir la suivante, en journalisant', function () {
    $nextYear = app(CreateSchoolYear::class)->handle(2027);
    $changeStatus = app(ChangeSchoolYearStatus::class);

    $changeStatus->close($this->currentYear);
    $changeStatus->open($nextYear);

    expect($this->currentYear->fresh()->status)->toBe(SchoolYearStatus::Closed)
        ->and($nextYear->fresh()->status)->toBe(SchoolYearStatus::Open)
        ->and(AuditLog::where('action', 'school_year.closed')->exists())->toBeTrue();
});

it('ne rouvre pas une année clôturée', function () {
    $changeStatus = app(ChangeSchoolYearStatus::class);
    $changeStatus->close($this->currentYear);

    $changeStatus->open($this->currentYear->fresh());
})->throws(BusinessRuleViolation::class);

it('prépare l\'année suivante avec les mêmes classes, coefficients et enseignants', function () {
    $teacher = StaffMember::factory()->create();
    $class = SchoolClass::create([
        'school_year_id' => $this->currentYear->id,
        'level_id' => Level::firstWhere('code', '6E')->id,
        'name' => '6e A',
        'head_teacher_id' => $teacher->id,
    ]);
    ClassSubject::create([
        'school_class_id' => $class->id,
        'subject_id' => Subject::firstWhere('code', 'MATH')->id,
        'coefficient' => '4',
        'teacher_id' => $teacher->id,
    ]);

    $nextYear = app(PrepareNextSchoolYear::class)->handle($this->currentYear);

    $copy = $nextYear->schoolClasses()->with('classSubjects')->sole();

    expect($nextYear->name)->toBe('2027-2028')
        ->and($nextYear->status)->toBe(SchoolYearStatus::Draft)
        ->and($nextYear->terms()->count())->toBe(3)
        ->and($copy->name)->toBe('6e A')
        ->and($copy->uuid)->not->toBe($class->uuid)
        ->and($copy->head_teacher_id)->toBe($teacher->id)
        ->and($copy->classSubjects->sole()->coefficient)->toBe('4.00')
        ->and($copy->classSubjects->sole()->teacher_id)->toBe($teacher->id)
        ->and($class->classSubjects()->count())->toBe(1);
});
