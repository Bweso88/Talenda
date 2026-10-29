<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academic\Actions\GrantStaffAccess;
use App\Modules\Academic\Enums\StaffPosition;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Stream;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Actions\SaveScores;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\AssessmentType;
use App\Modules\Core\Actions\ProvisionTenant;
use App\Modules\Core\Actions\SyncRolesAndPermissions;
use App\Modules\Core\Models\PlatformAdmin;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Support\SchoolClock;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Enrollments\Models\Student;
use App\Modules\Finances\Actions\CashDesk;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\FeeType;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\Payment;
use App\Modules\ParentPortal\Actions\GrantParentAccess;
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
use App\Modules\SchoolLife\Support\ClassRoll;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration : deux écoles congolaises et leurs comptes,
 * pour vérifier à la main que chaque école ne voit que ses données.
 *
 * Mot de passe de tous les comptes de démo : « talenda-demo ».
 */
class DatabaseSeeder extends Seeder
{
    public function run(SyncRolesAndPermissions $syncRolesAndPermissions, ProvisionTenant $provisionTenant): void
    {
        $syncRolesAndPermissions->handle();

        PlatformAdmin::query()->firstOrCreate(
            ['email' => 'admin@talenda.test'],
            ['name' => 'Super Admin Talenda', 'password' => 'talenda-demo'],
        );

        $brazzaville = Tenant::query()->firstWhere('slug', 'lycee-demo-brazzaville')
            ?? $provisionTenant->handle(
                school: ['name' => 'Lycée Démo de Brazzaville', 'slug' => 'lycee-demo-brazzaville', 'cycles' => ['COLLEGE', 'LYCEE']],
                admin: ['name' => 'Directeur Brazzaville', 'email' => 'directeur@brazzaville.test', 'phone' => '+242061111111', 'password' => 'talenda-demo'],
            );

        $pointeNoire = Tenant::query()->firstWhere('slug', 'college-demo-pointe-noire')
            ?? $provisionTenant->handle(
                school: ['name' => 'Collège Démo de Pointe-Noire', 'slug' => 'college-demo-pointe-noire', 'cycles' => ['PRIMAIRE', 'COLLEGE']],
                admin: ['name' => 'Directrice Pointe-Noire', 'email' => 'directrice@pointe-noire.test', 'phone' => '+242052222222', 'password' => 'talenda-demo'],
            );

        // Un parent avec un enfant dans chaque école : un seul compte,
        // deux appartenances.
        if (! User::query()->where('phone', '+242063333333')->exists()) {
            User::factory()
                ->memberOf($brazzaville, 'parent')
                ->memberOf($pointeNoire, 'parent')
                ->create([
                    'name' => 'Prisca Mabiala',
                    'phone' => '+242063333333',
                    'email' => null,
                    'password' => 'talenda-demo',
                ]);
        }

        app(TenantContext::class)->run($brazzaville, function (): void {
            $this->seedDemoClasses();
            $this->seedDemoFeeGrid();
            $this->seedDemoStudents();
            $this->seedDemoPayments();
            $this->seedDemoGrades();
            $this->seedDemoSchoolLife();
            $this->seedDemoParentAccess();
        });

        app(TenantContext::class)->run($brazzaville, fn () => $brazzaville->settings()->update([
            'city' => 'Brazzaville',
            'director_name' => 'M. Directeur Démo',
            'motto' => 'Discipline - Travail - Réussite',
            'phone' => '+242061111111',
        ]));

        User::query()
            ->whereIn('phone', ['+242061111111', '+242052222222', '+242063333333'])
            ->update(['must_change_password' => false]);

        app(TenantContext::class)->set(null);
    }

    /**
     * Quelques enseignants et classes avec leurs coefficients, pour avoir
     * un back-office réaliste. Coefficients indicatifs, à remplacer par
     * ceux de l'école pilote.
     */
    private function seedDemoClasses(): void
    {
        $schoolYear = SchoolYear::query()->open()->firstOrFail();

        if ($schoolYear->schoolClasses()->exists()) {
            return;
        }

        $teachers = collect([
            ['first_name' => 'Grâce', 'last_name' => 'Mabiala', 'phone' => '+242064444401'],
            ['first_name' => 'Arsène', 'last_name' => 'Nkounkou', 'phone' => '+242064444402'],
            ['first_name' => 'Prisca', 'last_name' => 'Okemba', 'phone' => '+242064444403'],
        ])->map(fn (array $teacher): StaffMember => StaffMember::create([...$teacher, 'position' => StaffPosition::Teacher]));

        $subjects = Subject::query()->pluck('id', 'code');
        $levels = Level::query()->pluck('id', 'code');
        $streams = Stream::query()->pluck('id', 'code');

        $classes = [
            ['name' => '6e A', 'level' => '6E', 'stream' => null, 'coefficients' => ['FR' => 3, 'MATH' => 3, 'ANG' => 2, 'HG' => 2, 'SVT' => 2, 'PC' => 1, 'ECM' => 1, 'EPS' => 1]],
            ['name' => '2nde C', 'level' => '2ND', 'stream' => 'C', 'coefficients' => ['MATH' => 5, 'PC' => 4, 'SVT' => 2, 'FR' => 3, 'ANG' => 2, 'HG' => 2, 'EPS' => 1]],
            ['name' => 'Tle D', 'level' => 'TLE', 'stream' => 'D', 'coefficients' => ['MATH' => 4, 'SVT' => 5, 'PC' => 4, 'FR' => 2, 'PHILO' => 2, 'ANG' => 2, 'HG' => 2, 'EPS' => 1]],
        ];

        foreach ($classes as $index => $class) {
            $schoolClass = SchoolClass::create([
                'school_year_id' => $schoolYear->id,
                'level_id' => $levels[$class['level']],
                'stream_id' => $class['stream'] === null ? null : $streams[$class['stream']],
                'name' => $class['name'],
                'capacity' => 60,
                'head_teacher_id' => $teachers[$index]->id,
            ]);

            foreach ($class['coefficients'] as $code => $coefficient) {
                ClassSubject::create([
                    'school_class_id' => $schoolClass->id,
                    'subject_id' => $subjects[$code],
                    'coefficient' => $coefficient,
                    'teacher_id' => in_array($code, ['MATH', 'PC'], true) ? $teachers[1]->id : null,
                ]);
            }
        }
    }

    /**
     * Une dizaine d'élèves inscrits, dont une fratrie (même mère).
     */
    private function seedDemoStudents(): void
    {
        if (Student::query()->exists()) {
            return;
        }

        $classes = SchoolClass::query()->pluck('id', 'name');
        $enroll = app(EnrollNewStudent::class);
        // La mère de la fratrie est le compte « Parent Démo » (app parents).
        $mother = ['relationship' => 'mother', 'last_name' => 'Mabiala', 'first_names' => 'Prisca', 'phone' => '+242063333333', 'is_financially_responsible' => true];

        $students = [
            ['Mabiala', 'Grâce Divine', 'F', '2014-05-12', '6e A', $mother],
            ['Mabiala', 'Arsène', 'M', '2010-01-20', '2nde C', $mother],
            ['Nkounkou', 'Merveille', 'F', '2014-09-03', '6e A', ['relationship' => 'father', 'last_name' => 'Nkounkou', 'first_names' => 'Jean', 'phone' => '+242055550002']],
            ['Ibara', 'Christ-Vianney', 'M', '2013-11-28', '6e A', ['relationship' => 'father', 'last_name' => 'Ibara', 'first_names' => 'Paul', 'phone' => '+242065550003']],
            ['Moukala', 'Dieuveille', 'M', '2010-06-15', '2nde C', ['relationship' => 'mother', 'last_name' => 'Moukala', 'first_names' => 'Sylvie', 'phone' => '+242065550004']],
            ['Ngoma', 'Exaucée', 'F', '2008-02-07', 'Tle D', ['relationship' => 'guardian', 'last_name' => 'Ngoma', 'first_names' => 'Albert', 'phone' => '+242055550005']],
            ['Samba', 'Rodrigue', 'M', '2008-08-19', 'Tle D', ['relationship' => 'father', 'last_name' => 'Samba', 'first_names' => 'Michel', 'phone' => '+242065550006']],
        ];

        foreach ($students as [$lastName, $firstNames, $gender, $birthDate, $className, $guardian]) {
            $enroll->handle(
                ['last_name' => $lastName, 'first_names' => $firstNames, 'gender' => $gender, 'birth_date' => $birthDate, 'birth_place' => 'Brazzaville'],
                [$guardian],
                SchoolClass::query()->findOrFail($classes[$className]),
                documents: ['birth_certificate', 'photos'],
            );
        }

        // Inscrits à la rentrée, pas le jour où la démonstration est créée.
        Enrollment::query()->update(['enrolled_on' => SchoolYear::query()->open()->value('starts_on')]);
    }

    /**
     * Grille tarifaire indicative (en FCFA), à remplacer par celle de
     * l'école pilote.
     */
    private function seedDemoFeeGrid(): void
    {
        $schoolYearId = SchoolYear::query()->open()->value('id');
        $feeTypes = FeeType::query()->pluck('id', 'code');

        $grid = [
            '6E' => 180000, '5E' => 180000, '4E' => 190000, '3E' => 210000,
            '2ND' => 240000, '1RE' => 250000, 'TLE' => 270000,
        ];

        foreach (Level::query()->whereIn('code', array_keys($grid))->get() as $level) {
            foreach (['INSCRIPTION' => 25000, 'REINSCRIPTION' => 15000, 'SCOLARITE' => $grid[$level->code], 'TENUE' => 12500] as $code => $amount) {
                FeeAmount::query()->firstOrCreate(
                    ['school_year_id' => $schoolYearId, 'level_id' => $level->id, 'fee_type_id' => $feeTypes[$code]],
                    ['amount' => $amount],
                );
            }
        }
    }

    /**
     * Un caissier avec une caisse ouverte et quelques encaissements.
     */
    private function seedDemoPayments(): void
    {
        if (Payment::query()->exists()) {
            return;
        }

        $cashierStaff = StaffMember::create([
            'first_name' => 'Daniel',
            'last_name' => 'Bouanga',
            'position' => StaffPosition::Cashier,
            'phone' => '+242064444410',
        ]);
        app(GrantStaffAccess::class)->handle($cashierStaff, 'cashier');
        $cashier = User::query()->where('phone', '+242064444410')->firstOrFail();
        $cashier->update(['password' => 'talenda-demo', 'must_change_password' => false]);

        app(CashDesk::class)->open(CashRegister::query()->firstOrFail(), 20000, $cashier);

        $invoices = Invoice::query()->with('student')->orderBy('id')->get();
        $record = app(RecordPayment::class);

        foreach ($invoices->take(5) as $index => $invoice) {
            $record->handle($invoice, [70000, 45000, 25000, 120000, 25000][$index], PaymentMethod::Cash, $cashier, payerName: 'Parent de '.$invoice->student->first_names);
        }

        $record->handle($invoices[1], 20000, PaymentMethod::MobileMoney, $cashier, 'MP261028.0915.B71234');
    }

    /**
     * Notes du premier trimestre (devoir + composition) dans toutes les
     * matières, et un compte enseignant pour essayer la saisie.
     */
    private function seedDemoGrades(): void
    {
        if (Assessment::query()->exists()) {
            return;
        }

        $teacherStaff = StaffMember::query()->where('phone', '+242064444402')->firstOrFail();
        app(GrantStaffAccess::class)->handle($teacherStaff, 'teacher');
        User::query()->where('phone', '+242064444402')->update(['password' => Hash::make('talenda-demo'), 'must_change_password' => false]);

        $term = Term::query()->orderBy('position')->firstOrFail();
        $types = AssessmentType::query()->pluck('id', 'code');
        $faker = fake('fr_FR');
        $faker->seed(2026);

        foreach (ClassSubject::query()->with('schoolClass')->get() as $classSubject) {
            $enrollments = $classSubject->schoolClass->enrollments()->pluck('id');

            foreach (['DEVOIR' => 'Devoir n° 1', 'COMPOSITION' => 'Composition du 1er trimestre'] as $code => $title) {
                $assessment = Assessment::create([
                    'class_subject_id' => $classSubject->id,
                    'term_id' => $term->id,
                    'assessment_type_id' => $types[$code],
                    'title' => $title,
                    'held_on' => $term->starts_on->copy()->addWeeks($code === 'DEVOIR' ? 4 : 9),
                    'max_score' => 20,
                ]);

                app(SaveScores::class)->handle($assessment, $enrollments->mapWithKeys(fn (int $id): array => [
                    $id => ['value' => $faker->randomElement([6, 8, 9.5, 10, 11, 12, 12.5, 13, 14, 15, 16.5, 17])],
                ])->all());
            }
        }
    }

    /**
     * Surveillant général, appels des derniers jours de classe avec
     * quelques absences et retards, un justificatif et un incident.
     */
    private function seedDemoSchoolLife(): void
    {
        if (AttendanceSession::query()->exists()) {
            return;
        }

        $supervisorStaff = StaffMember::create([
            'first_name' => 'Rodrigue',
            'last_name' => 'Ngoma',
            'position' => StaffPosition::Supervisor,
            'phone' => '+242064444420',
        ]);
        app(GrantStaffAccess::class)->handle($supervisorStaff, 'supervisor');
        $supervisor = User::query()->where('phone', '+242064444420')->firstOrFail();
        $supervisor->update(['password' => 'talenda-demo', 'must_change_password' => false]);

        $faker = fake('fr_FR');
        $faker->seed(242);
        $slots = AttendanceSlot::query()->orderBy('position')->get();
        $year = SchoolYear::query()->open()->firstOrFail();
        $days = collect(range(1, 10))
            ->map(fn (int $daysAgo) => SchoolClock::today()->subDays($daysAgo))
            ->filter(fn (Carbon $day): bool => $day->isWeekday() && $day->gte($year->starts_on))
            ->take(5);

        foreach (SchoolClass::query()->get() as $schoolClass) {
            foreach ($days as $day) {
                foreach ($slots as $slot) {
                    $entries = ClassRoll::on($schoolClass, $day)->mapWithKeys(fn ($enrollment): array => [$enrollment->id => match (true) {
                        $faker->boolean(8) => ['status' => 'absent'],
                        $faker->boolean(6) => ['status' => 'late', 'late_minutes' => $faker->randomElement([5, 10, 15, 30])],
                        default => ['status' => 'present'],
                    }])->all();

                    app(TakeAttendance::class)->handle($schoolClass, $day, $slot, $entries, $supervisor);
                }
            }
        }

        $absence = AttendanceRecord::query()->where('status', AttendanceStatus::Absent)->with(['session', 'enrollment'])->first();

        if ($absence !== null) {
            app(JustifyAbsences::class)->handle($absence->enrollment, $absence->session->held_on, $absence->session->held_on, JustificationReason::Illness, 'Certificat médical du CHU de Brazzaville', $supervisor);
        }

        $enrollment = Enrollment::query()->orderByDesc('id')->firstOrFail();
        app(RecordIncident::class)->handle([
            'enrollment_id' => $enrollment->id,
            'occurred_on' => $days->first() ?? SchoolClock::today(),
            'description' => 'Usage du téléphone portable en classe malgré un premier rappel.',
            'sanction' => Sanction::Warning,
            'parents_informed' => true,
        ], $supervisor);
    }

    /**
     * Ouvre l'app parents à la mère de la fratrie (compte 06 333 33 33) et
     * lui fait déposer un justificatif, en attente d'examen.
     */
    private function seedDemoParentAccess(): void
    {
        $guardian = Guardian::query()->where('phone', '+242063333333')->firstOrFail();

        if ($guardian->user_id !== null) {
            return;
        }

        app(GrantParentAccess::class)->handle($guardian);
        $parent = User::query()->where('phone', '+242063333333')->firstOrFail();

        $absence = AttendanceRecord::query()
            ->where('status', AttendanceStatus::Absent)
            ->whereIn('enrollment_id', Enrollment::query()->whereHas('student.guardians', fn ($query) => $query->whereKey($guardian->id))->select('id'))
            ->with(['session', 'enrollment'])
            ->get()
            ->first(fn (AttendanceRecord $record): bool => AbsenceJustification::query()->where('enrollment_id', $record->enrollment_id)->doesntExist());

        if ($absence !== null) {
            app(JustifyAbsences::class)->submitByGuardian($absence->enrollment, $absence->session->held_on, $absence->session->held_on, JustificationReason::Family, 'Décès d\'un proche, il était aux obsèques au village.', $parent);
        }
    }
}
