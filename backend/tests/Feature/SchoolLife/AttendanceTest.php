<?php

use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\TeacherIdentity;
use App\Modules\Assessments\Actions\ManageTerm;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Student;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Actions\RecordIncident;
use App\Modules\SchoolLife\Actions\TakeAttendance;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Enums\Sanction;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use App\Modules\SchoolLife\Models\AttendanceSession;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use App\Modules\SchoolLife\Support\AttendanceTotals;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Lundi 16 novembre 2026, 10 h à Brazzaville : premier trimestre.
    $this->travelTo(Carbon::parse('2026-11-16 09:00', 'UTC'));
    TeacherIdentity::flush();

    [$this->tenant, $this->admin] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->year = SchoolYear::query()->open()->sole();
    $this->term = $this->year->terms()->orderBy('position')->first();
    $this->class = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    $this->otherClass = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e B']);
    $this->morning = AttendanceSlot::firstWhere('name', 'Matin');
    $this->afternoon = AttendanceSlot::firstWhere('name', 'Après-midi');

    [$this->ange, $this->bertin, $this->clarisse] = collect(['Ange', 'Bertin', 'Clarisse'])->map(fn (string $name) => Enrollment::factory()->in($this->class)->create([
        'student_id' => Student::factory()->create(['last_name' => 'ELEVE', 'first_names' => $name])->id,
        'enrolled_on' => '2026-10-01',
    ]))->all();
});

function takeAttendance(string $date, AttendanceSlot $slot, array $entries, ?User $user = null, ?SchoolClass $class = null): void
{
    app(TakeAttendance::class)->handle($class ?? test()->class, Carbon::parse($date), $slot, $entries, $user ?? test()->admin);
}

function teacherOf(SchoolClass $class): User
{
    $user = User::factory()->memberOf(test()->tenant, 'teacher')->create();
    $staff = StaffMember::factory()->create();
    $staff->membership()->associate($user->memberships()->first())->save();
    ClassSubject::create(['school_class_id' => $class->id, 'subject_id' => Subject::firstWhere('code', 'MATH')->id, 'coefficient' => 4, 'teacher_id' => $staff->id]);

    return $user;
}

it('crée les créneaux d\'appel du Congo à la création de l\'école', function () {
    expect(AttendanceSlot::orderBy('position')->get()->map(fn (AttendanceSlot $slot) => $slot->label())->all())
        ->toBe(['Matin (07:30 – 12:30)', 'Après-midi (14:30 – 17:30)']);
});

it('n\'enregistre que les absents et les retards, et se corrige en refaisant l\'appel', function () {
    takeAttendance('2026-11-16', $this->morning, [
        $this->ange->id => ['status' => 'present'],
        $this->bertin->id => ['status' => AttendanceStatus::Absent],
        $this->clarisse->id => ['status' => 'present', 'late_minutes' => 15],
    ]);

    expect(AttendanceRecord::count())->toBe(2)
        ->and(AttendanceRecord::firstWhere('enrollment_id', $this->clarisse->id))
        ->status->toBe(AttendanceStatus::Late)
        ->late_minutes->toBe(15)
        ->and(AttendanceRecord::first()->session->duration_minutes)->toBe(300);

    // Bertin est finalement arrivé : on corrige le même appel.
    takeAttendance('2026-11-16', $this->morning, [$this->bertin->id => ['status' => 'present']]);

    expect(AttendanceRecord::pluck('enrollment_id')->all())->toBe([$this->clarisse->id]);
});

it('refuse un appel hors de l\'année, dans le futur ou sur une période publiée', function (string $date, string $message) {
    expect(fn () => takeAttendance($date, $this->morning, []))
        ->toThrow(BusinessRuleViolation::class, $message);
})->with([
    'futur' => ['2026-11-17', 'pas encore arrivé'],
    'avant l\'année' => ['2026-09-15', 'en dehors de l\'année'],
]);

it('refuse de modifier les absences d\'un trimestre dont les bulletins sont publiés', function () {
    $this->term->update(['status' => TermStatus::Published]);

    expect(fn () => takeAttendance('2026-11-16', $this->morning, []))
        ->toThrow(BusinessRuleViolation::class, 'publiés');
});

it('refuse un élève qui n\'était pas dans la classe ce jour-là', function () {
    $this->bertin->update(['status' => EnrollmentStatus::Withdrawn, 'left_on' => '2026-11-10']);

    expect(fn () => takeAttendance('2026-11-16', $this->morning, [$this->bertin->id => ['status' => 'absent']]))
        ->toThrow(BusinessRuleViolation::class, 'n\'était pas inscrit');

    // Avant son départ, il figurait bien sur la liste.
    takeAttendance('2026-11-09', $this->morning, [$this->bertin->id => ['status' => 'absent']]);
    expect(AttendanceRecord::count())->toBe(1);
});

it('limite l\'enseignant à ses classes, le jour même', function () {
    $teacher = teacherOf($this->class);
    app(TenantContext::class)->set($this->tenant);

    takeAttendance('2026-11-16', $this->morning, [$this->ange->id => ['status' => 'absent']], $teacher);
    expect(AttendanceRecord::count())->toBe(1);

    expect(fn () => takeAttendance('2026-11-13', $this->morning, [], $teacher))
        ->toThrow(BusinessRuleViolation::class, 'le jour même')
        ->and(fn () => takeAttendance('2026-11-16', $this->morning, [], $teacher, $this->otherClass))
        ->toThrow(BusinessRuleViolation::class, 'vos classes');
});

it('autorise le professeur principal à faire l\'appel de sa classe', function () {
    $user = User::factory()->memberOf($this->tenant, 'teacher')->create();
    $staff = StaffMember::factory()->create();
    $staff->membership()->associate($user->memberships()->first())->save();
    $this->otherClass->update(['head_teacher_id' => $staff->id]);

    takeAttendance('2026-11-16', $this->afternoon, [], $user, $this->otherClass);

    expect(AttendanceSession::where('school_class_id', $this->otherClass->id)->exists())->toBeTrue();
});

it('compte les heures d\'absence, les justifie par période et compte les retards', function () {
    takeAttendance('2026-11-12', $this->morning, [$this->ange->id => ['status' => 'absent']]);
    takeAttendance('2026-11-12', $this->afternoon, [$this->ange->id => ['status' => 'absent']]);
    takeAttendance('2026-11-16', $this->morning, [$this->ange->id => ['status' => 'absent'], $this->bertin->id => ['status' => 'late']]);

    $justification = app(JustifyAbsences::class)->handle($this->ange, Carbon::parse('2026-11-12'), Carbon::parse('2026-11-12'), JustificationReason::Illness, 'Certificat médical', $this->admin);

    $totals = AttendanceTotals::between([$this->ange->id, $this->bertin->id, $this->clarisse->id], $this->term->starts_on, $this->term->ends_on);

    expect(JustifyAbsences::coveredAbsences($justification))->toBe(2)
        ->and($totals[$this->ange->id])
        ->absenceMinutes->toBe(300 + 180 + 300)
        ->justifiedAbsenceMinutes->toBe(480)
        ->lateCount->toBe(0)
        ->and($totals[$this->bertin->id]->lateCount)->toBe(1)
        ->and($totals)->not->toHaveKey($this->clarisse->id)
        ->and(AuditLog::where('action', 'absence.justified')->exists())->toBeTrue();

    app(JustifyAbsences::class)->withdraw($justification, $this->admin);

    expect(AttendanceTotals::between([$this->ange->id], $this->term->starts_on, $this->term->ends_on)[$this->ange->id]->justifiedAbsenceMinutes)->toBe(0)
        ->and(AbsenceJustification::count())->toBe(0);
});

it('refuse un justificatif dont la fin précède le début', function () {
    expect(fn () => app(JustifyAbsences::class)->handle($this->ange, Carbon::parse('2026-11-12'), Carbon::parse('2026-11-10'), JustificationReason::Family, null, $this->admin))
        ->toThrow(BusinessRuleViolation::class, 'avant sa date de début');
});

it('fige les absences sur le bulletin à la publication', function () {
    takeAttendance('2026-11-16', $this->morning, [$this->ange->id => ['status' => 'absent'], $this->bertin->id => ['status' => 'late', 'late_minutes' => 10]]);

    $this->term->update(['status' => TermStatus::Locked]);
    app(ManageTerm::class)->publish($this->term->refresh(), $this->admin);

    $ange = TermResult::firstWhere('enrollment_id', $this->ange->id);

    expect($ange->absence_minutes)->toBe(300)
        ->and($ange->justified_absence_minutes)->toBe(0)
        ->and($ange->absencesLabel())->toBe('5 h, non justifiées')
        ->and(TermResult::firstWhere('enrollment_id', $this->bertin->id)->late_count)->toBe(1);

    // Un justificatif apporté après la publication ne change pas le bulletin publié.
    app(JustifyAbsences::class)->handle($this->ange, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-16'), JustificationReason::Illness, null, $this->admin);

    expect($ange->refresh()->justified_absence_minutes)->toBe(0);
});

it('enregistre un incident de discipline et exige la durée d\'une exclusion', function () {
    $data = ['enrollment_id' => $this->ange->id, 'occurred_on' => '2026-11-13', 'description' => 'Bagarre dans la cour', 'sanction' => 'suspension'];

    expect(fn () => app(RecordIncident::class)->handle($data, $this->admin))
        ->toThrow(BusinessRuleViolation::class, 'nombre de jours');

    $incident = app(RecordIncident::class)->handle([...$data, 'suspension_days' => 3, 'parents_informed' => true], $this->admin);

    expect($incident->sanction)->toBe(Sanction::Suspension)
        ->and($incident->suspension_days)->toBe(3)
        ->and($incident->reported_by)->toBe($this->admin->id)
        ->and(AuditLog::where('action', 'discipline.recorded')->exists())->toBeTrue();

    // Passer à un simple avertissement efface la durée d'exclusion.
    app(RecordIncident::class)->handle([...$data, 'sanction' => Sanction::Warning, 'suspension_days' => 3], $this->admin, $incident);

    expect($incident->refresh()->suspension_days)->toBeNull();
});
