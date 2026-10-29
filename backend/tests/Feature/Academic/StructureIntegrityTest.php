<?php

use App\Modules\Academic\Actions\CopyClassSubjects;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\QueryException;

/*
 * Règles garanties par la base elle-même, indépendamment du code PHP.
 */
beforeEach(function () {
    [$this->schoolA] = provisionSchool('ecole-a');
    [$this->schoolB] = provisionSchool('ecole-b');

    $this->context = app(TenantContext::class);
    $this->context->set($this->schoolA);

    $this->year = SchoolYear::query()->open()->sole();
    $this->class = SchoolClass::create([
        'school_year_id' => $this->year->id,
        'level_id' => Level::firstWhere('code', '6E')->id,
        'name' => '6e A',
    ]);
});

it('refuse de rattacher une classe au niveau d\'une autre école', function () {
    $levelOfB = $this->context->run($this->schoolB, fn () => Level::firstWhere('code', '6E'));

    SchoolClass::create([
        'school_year_id' => $this->year->id,
        'level_id' => $levelOfB->id,
        'name' => '6e B',
    ]);
})->throws(QueryException::class);

it('refuse d\'affecter un enseignant d\'une autre école', function () {
    $teacherOfB = $this->context->run($this->schoolB, fn () => StaffMember::factory()->create());

    ClassSubject::create([
        'school_class_id' => $this->class->id,
        'subject_id' => Subject::firstWhere('code', 'MATH')->id,
        'coefficient' => 2,
        'teacher_id' => $teacherOfB->id,
    ]);
})->throws(QueryException::class);

it('refuse un coefficient nul', function () {
    ClassSubject::create([
        'school_class_id' => $this->class->id,
        'subject_id' => Subject::firstWhere('code', 'MATH')->id,
        'coefficient' => 0,
    ]);
})->throws(QueryException::class);

it('refuse deux classes du même nom la même année', function () {
    SchoolClass::create([
        'school_year_id' => $this->year->id,
        'level_id' => Level::firstWhere('code', '5E')->id,
        'name' => '6e A',
    ]);
})->throws(QueryException::class);

it('retire l\'enseignant des matières quand il est supprimé, sans toucher à l\'école', function () {
    $teacher = StaffMember::factory()->create();
    $classSubject = ClassSubject::create([
        'school_class_id' => $this->class->id,
        'subject_id' => Subject::firstWhere('code', 'MATH')->id,
        'coefficient' => 3,
        'teacher_id' => $teacher->id,
    ]);
    $this->class->update(['head_teacher_id' => $teacher->id]);

    $teacher->delete();

    expect($classSubject->fresh()->teacher_id)->toBeNull()
        ->and($classSubject->fresh()->tenant_id)->toBe($this->schoolA->id)
        ->and($this->class->fresh()->head_teacher_id)->toBeNull();
});

it('copie les matières d\'une classe modèle sans doublon ni enseignant', function () {
    $teacher = StaffMember::factory()->create();
    $math = Subject::firstWhere('code', 'MATH');
    $french = Subject::firstWhere('code', 'FR');
    ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => $math->id, 'coefficient' => 4, 'teacher_id' => $teacher->id]);
    ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => $french->id, 'coefficient' => 3]);

    $target = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => $this->class->level_id, 'name' => '6e B']);
    ClassSubject::create(['school_class_id' => $target->id, 'subject_id' => $math->id, 'coefficient' => 5]);

    $added = app(CopyClassSubjects::class)->handle($this->class, $target);

    expect($added)->toBe(1)
        ->and($target->classSubjects()->count())->toBe(2)
        ->and($target->classSubjects()->where('subject_id', $math->id)->value('coefficient'))->toBe('5.00')
        ->and($target->classSubjects()->whereNotNull('teacher_id')->exists())->toBeFalse()
        ->and($target->totalCoefficient())->toBe(8.0);
});
