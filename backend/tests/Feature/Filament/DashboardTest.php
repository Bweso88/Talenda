<?php

use App\Filament\School\Support\DashboardFigures;
use App\Models\User;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\TeacherIdentity;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\Invoice;
use App\Modules\SchoolLife\Actions\TakeAttendance;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16 09:00', 'UTC'));
    TeacherIdentity::flush();
    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);
    feeGrid('6E');

    $year = SchoolYear::query()->open()->sole();
    $this->class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    [$this->first, $this->second] = collect(['Grâce', 'Arsène'])->map(fn (string $name): Enrollment => app(EnrollNewStudent::class)->handle(
        newStudentData(['first_names' => $name, 'birth_date' => $name === 'Grâce' ? '2014-05-12' : '2013-02-02']),
        [['relationship' => 'mother', 'last_name' => 'Mabiala', 'first_names' => 'Prisca', 'phone' => '+24206555000'.($name === 'Grâce' ? '1' : '2')]],
        $this->class,
    ))->all();
    Enrollment::query()->update(['enrolled_on' => '2026-10-01']);
});

it('calcule les présences du jour, l\'encaissé et le taux de recouvrement', function () {
    app(TakeAttendance::class)->handle($this->class, Carbon::parse('2026-11-16'), AttendanceSlot::firstWhere('name', 'Matin'), [$this->first->id => ['status' => 'absent']], $this->admin);
    app(RecordPayment::class)->handle(Invoice::firstWhere('enrollment_id', $this->first->id), 50000, PaymentMethod::MobileMoney, $this->admin, 'MP1');

    $figures = new DashboardFigures;

    expect($figures->enrolled())->toBe(2)
        ->and($figures->attendanceToday())->toBe(['called' => 2, 'absent' => 1, 'late' => 0])
        ->and($figures->finance())->toMatchArray(['billed' => 410000, 'collected' => 50000, 'month' => 50000, 'today' => 50000])
        ->and($figures->byCycle())->toBe([['name' => 'Collège', 'count' => 2]])
        ->and($figures->monthlyCollections())->toHaveKey('nov. 26', 50000);
});

it('affiche le tableau de bord de la direction', function () {
    $this->actingAs($this->admin)->get('/ecole/lycee-a')
        ->assertOk()
        ->assertSee('Bonjour')
        ->assertSee('Faire l&#039;appel', false)
        ->assertSeeLivewire('app.filament.school.widgets.key-figures')
        ->assertSeeLivewire('app.filament.school.widgets.priority-actions');
});

it('adapte le tableau de bord au rôle', function (string $role, array $visible, array $hidden) {
    $user = User::factory()->memberOf($this->school, $role)->create();

    if ($role === 'teacher') {
        $staff = StaffMember::factory()->create();
        $staff->membership()->associate($user->memberships()->first())->save();
        ClassSubject::create(['school_class_id' => $this->class->id, 'subject_id' => Subject::firstWhere('code', 'MATH')->id, 'coefficient' => 4, 'teacher_id' => $staff->id]);
    }

    $response = $this->actingAs($user)->get('/ecole/lycee-a')->assertOk();

    foreach ($visible as $widget) {
        $response->assertSeeLivewire("app.filament.school.widgets.{$widget}");
    }

    foreach ($hidden as $widget) {
        $response->assertDontSeeLivewire("app.filament.school.widgets.{$widget}");
    }
})->with([
    'caissier' => ['cashier', ['key-figures', 'collections-chart'], ['teacher-today']],
    'enseignant' => ['teacher', ['teacher-today'], ['key-figures', 'collections-chart', 'recent-activity']],
    'surveillant' => ['supervisor', ['key-figures', 'priority-actions'], ['collections-chart', 'teacher-today']],
]);
