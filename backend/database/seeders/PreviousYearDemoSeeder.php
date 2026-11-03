<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academic\Actions\ChangeSchoolYearStatus;
use App\Modules\Academic\Actions\CreateSchoolYear;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Actions\ManageTerm;
use App\Modules\Assessments\Actions\SaveScores;
use App\Modules\Assessments\Actions\SaveTermComments;
use App\Modules\Assessments\Actions\YearEndDecisions;
use App\Modules\Assessments\Models\AnnualResult;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\AssessmentType;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Assessments\Support\ComputeClassTermResults;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\FeeType;
use App\Modules\Finances\Models\Invoice;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Actions\TakeAttendance;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Année scolaire 2025-2026 complète pour la démonstration, au Lycée Démo de
 * Brazzaville : une classe de 6e de dix élèves, de l'inscription à la
 * clôture — factures et paiements, deux évaluations par matière et par
 * trimestre, absences et justificatif, appréciations, bulletins publiés des
 * trois trimestres, moyennes annuelles, décisions de fin d'année.
 *
 * Tout est saisi par les actions de l'application, « à la date » des
 * événements (horloge déplacée), comme si l'école avait utilisé Talenda
 * toute l'année : les documents (bulletins, relevés de notes,
 * procès-verbaux, reçus) s'impriment ensuite depuis le back-office.
 *
 * Sans effet si l'année 2025-2026 existe déjà. À lancer une fois :
 * php artisan db:seed --class=PreviousYearDemoSeeder --force
 */
class PreviousYearDemoSeeder extends Seeder
{
    private const YEAR = 2025;

    /**
     * Nom, prénoms, sexe, date de naissance, niveau (de 8 à 16), responsable.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: float, 5: array{0: string, 1: string, 2: string, 3: string}}>
     */
    private const STUDENTS = [
        ['Bakala', 'Christ-Emmanuel', 'M', '2013-04-11', 15.5, ['father', 'Bakala', 'Didier', '+242067700101']],
        ['Biyoudi', 'Ornella', 'F', '2013-07-23', 13.0, ['mother', 'Biyoudi', 'Nadège', '+242067700102']],
        ['Ditsamou', 'Prince', 'M', '2012-12-02', 9.0, ['father', 'Ditsamou', 'Hervé', '+242067700103']],
        ['Ganga', 'Merveille', 'F', '2013-02-17', 14.5, ['mother', 'Ganga', 'Solange', '+242067700104']],
        ['Kimbembe', 'Jordy', 'M', '2013-09-05', 11.0, ['father', 'Kimbembe', 'Alain', '+242067700105']],
        ['Loubaki', 'Grâce', 'F', '2013-05-30', 12.0, ['mother', 'Loubaki', 'Clarisse', '+242067700106']],
        ['Makosso', 'Dan', 'M', '2012-10-14', 7.5, ['guardian', 'Makosso', 'Bernard', '+242067700107']],
        ['Mavoungou', 'Exaucée', 'F', '2013-01-08', 16.0, ['mother', 'Mavoungou', 'Pélagie', '+242067700108']],
        ['Nzaou', 'Rodrigue', 'M', '2013-03-26', 10.0, ['father', 'Nzaou', 'Firmin', '+242067700109']],
        ['Tchibinda', 'Dorcas', 'F', '2013-08-19', 12.5, ['mother', 'Tchibinda', 'Rosine', '+242067700110']],
    ];

    /**
     * Matière => [coefficient, enseignant (téléphone), difficulté].
     */
    private const SUBJECTS = [
        'FR' => [3, '+242064444401', 0.0],
        'MATH' => [3, '+242064444402', -1.0],
        'ANG' => [2, '+242064444403', 0.5],
        'HG' => [2, '+242064444401', 0.0],
        'SVT' => [2, '+242064444403', 0.5],
        'PC' => [1, '+242064444402', -0.5],
        'ECM' => [1, '+242064444401', 1.5],
        'EPS' => [1, '+242064444403', 2.0],
    ];

    private User $admin;

    private ?User $cashier;

    private int $seed = 2025;

    public function run(): void
    {
        $tenant = Tenant::query()->firstWhere('slug', 'lycee-demo-brazzaville');

        if ($tenant === null) {
            $this->command?->warn('École de démonstration introuvable : lancez d\'abord php artisan db:seed.');

            return;
        }

        app(TenantContext::class)->run($tenant, function (): void {
            if (SchoolYear::query()->where('name', self::YEAR.'-'.(self::YEAR + 1))->exists()) {
                $this->command?->info('L\'année 2025-2026 de démonstration existe déjà.');

                return;
            }

            $this->admin = User::query()->where('phone', '+242061111111')->firstOrFail();
            $this->cashier = User::query()->where('phone', '+242064444410')->first();

            try {
                DB::transaction(fn () => $this->seedYear());
            } finally {
                Carbon::setTestNow();
            }
        });
    }

    private function seedYear(): void
    {
        // Une seule année ouverte à la fois : l'année en cours est mise de
        // côté le temps de « revivre » 2025-2026, puis rouverte.
        $current = SchoolYear::query()->open()->first();
        $current?->forceFill(['status' => SchoolYearStatus::Draft])->save();

        $this->travel('2025-09-01 08:00');
        $year = app(CreateSchoolYear::class)->handle(self::YEAR);
        app(ChangeSchoolYearStatus::class)->open($year, $this->admin);
        $terms = $year->terms()->orderBy('position')->get();

        $schoolClass = $this->createClass($year);
        $this->feeGrid($year);

        $this->travel('2025-09-15 09:00');
        $enrollments = $this->enroll($schoolClass);
        $this->payments($enrollments);

        foreach ($terms as $term) {
            $this->term($term, $schoolClass, $enrollments);
        }

        $this->travel($year->ends_on->copy()->subDays(10)->format('Y-m-d').' 10:00');
        $this->decisions($schoolClass, $enrollments);
        app(ChangeSchoolYearStatus::class)->close($year->fresh(), $this->admin);

        $current?->forceFill(['status' => SchoolYearStatus::Open])->save();
    }

    private function createClass(SchoolYear $year): SchoolClass
    {
        $teachers = StaffMember::query()->whereIn('phone', array_column(self::SUBJECTS, 1))->get()->keyBy('phone');
        $subjects = Subject::query()->pluck('id', 'code');

        $schoolClass = SchoolClass::create([
            'school_year_id' => $year->id,
            'level_id' => Level::query()->where('code', '6E')->value('id'),
            'name' => '6e A',
            'capacity' => 60,
            'head_teacher_id' => $teachers->get('+242064444401')?->id,
        ]);

        foreach (self::SUBJECTS as $code => [$coefficient, $teacherPhone]) {
            ClassSubject::create([
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subjects[$code],
                'coefficient' => $coefficient,
                'teacher_id' => $teachers->get($teacherPhone)?->id,
            ]);
        }

        return $schoolClass;
    }

    private function feeGrid(SchoolYear $year): void
    {
        $levelId = Level::query()->where('code', '6E')->value('id');

        foreach (FeeType::query()->whereIn('code', ['INSCRIPTION', 'REINSCRIPTION', 'SCOLARITE', 'TENUE'])->pluck('id', 'code') as $code => $feeTypeId) {
            FeeAmount::query()->firstOrCreate(
                ['school_year_id' => $year->id, 'level_id' => $levelId, 'fee_type_id' => $feeTypeId],
                ['amount' => ['INSCRIPTION' => 25000, 'REINSCRIPTION' => 15000, 'SCOLARITE' => 170000, 'TENUE' => 12500][$code]],
            );
        }
    }

    /**
     * @return Collection<int, array{enrollment: Enrollment, level: float}>
     */
    private function enroll(SchoolClass $schoolClass): Collection
    {
        return collect(self::STUDENTS)->map(function (array $student) use ($schoolClass): array {
            [$lastName, $firstNames, $gender, $birthDate, $level, [$relationship, $guardianLastName, $guardianFirstNames, $phone]] = $student;

            $enrollment = app(EnrollNewStudent::class)->handle(
                ['last_name' => $lastName, 'first_names' => $firstNames, 'gender' => $gender, 'birth_date' => $birthDate, 'birth_place' => 'Brazzaville'],
                [['relationship' => $relationship, 'last_name' => $guardianLastName, 'first_names' => $guardianFirstNames, 'phone' => $phone, 'is_financially_responsible' => true]],
                $schoolClass,
                documents: ['birth_certificate', 'photos'],
            );

            return ['enrollment' => $enrollment, 'level' => $level];
        });
    }

    /**
     * Paiements échelonnés de septembre à mai ; deux familles gardent un
     * reste à payer en fin d'année.
     *
     * @param  Collection<int, array{enrollment: Enrollment, level: float}>  $enrollments
     */
    private function payments(Collection $enrollments): void
    {
        $dates = ['2025-09-15', '2025-10-10', '2025-11-12', '2026-01-09', '2026-02-11', '2026-03-10', '2026-04-14', '2026-05-12'];
        $cashier = $this->cashier ?? $this->admin;

        foreach ($enrollments->values() as $index => ['enrollment' => $enrollment]) {
            $invoice = Invoice::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
            $total = $invoice->statement()->total;
            // Familles 3 et 7 : il reste respectivement 40 000 et 85 000 F.
            $toPay = $total - ([2 => 40000, 6 => 85000][$index] ?? 0);
            $instalments = $index % 3 === 0 ? 3 : 8;
            $share = (int) (floor($toPay / $instalments / 500) * 500);

            foreach (array_slice($dates, 0, $instalments) as $number => $date) {
                $amount = $number === $instalments - 1 ? $toPay - $share * ($instalments - 1) : $share;
                $method = $number % 2 === 0 ? PaymentMethod::MobileMoney : PaymentMethod::BankTransfer;
                $paidOn = $instalments === 3 ? ['2025-09-15', '2026-01-09', '2026-04-14'][$number] : $date;

                $this->travel($paidOn.sprintf(' 10:%02d', $index * 5));
                app(RecordPayment::class)->handle(
                    $invoice->fresh(),
                    $amount,
                    $method,
                    $cashier,
                    $method === PaymentMethod::MobileMoney ? 'MP'.Carbon::parse($paidOn)->format('ymd').'.'.sprintf('%04d', 1000 + $index * 10 + $number) : 'VIR-'.Carbon::parse($paidOn)->format('Ymd').'-'.($index + 1),
                    "Parent de {$enrollment->student->first_names}",
                    Carbon::parse($paidOn),
                );
            }
        }
    }

    /**
     * Un trimestre : appel avec quelques absences, un devoir et une
     * composition par matière, appréciations, verrouillage, publication.
     *
     * @param  Collection<int, array{enrollment: Enrollment, level: float}>  $enrollments
     */
    private function term(Term $term, SchoolClass $schoolClass, Collection $enrollments): void
    {
        $classSubjects = $schoolClass->classSubjects()->with('subject')->get();
        $types = AssessmentType::query()->pluck('id', 'code');
        $trend = [1 => 0.0, 2 => 0.4, 3 => 0.8][$term->position] ?? 0.0;

        $this->attendance($term, $schoolClass, $enrollments);

        foreach ([['DEVOIR', "Devoir n° {$term->position}", $term->starts_on->copy()->addWeeks(4)], ['COMPOSITION', "Composition du {$term->name}", $term->ends_on->copy()->subDays(10)]] as [$code, $title, $heldOn]) {
            $heldOn = $this->weekday($heldOn);
            $this->travel($heldOn->format('Y-m-d').' 15:00');

            foreach ($classSubjects as $classSubject) {
                $assessment = Assessment::create([
                    'class_subject_id' => $classSubject->id,
                    'term_id' => $term->id,
                    'assessment_type_id' => $types[$code],
                    'title' => $title,
                    'held_on' => $heldOn,
                    'max_score' => 20,
                ]);

                $difficulty = self::SUBJECTS[$classSubject->subject->code][2] ?? 0.0;

                app(SaveScores::class)->handle($assessment, $enrollments->mapWithKeys(function (array $student, int $index) use ($difficulty, $trend, $code, $term): array {
                    // Un élève absent au devoir de mathématiques du 2e trimestre.
                    if ($index === 4 && $code === 'DEVOIR' && $term->position === 2 && $difficulty === -1.0) {
                        return [$student['enrollment']->id => ['status' => 'absent']];
                    }

                    $mark = $student['level'] + $difficulty + ($student['level'] < 11 ? $trend : $trend / 2) + $this->noise();

                    return [$student['enrollment']->id => ['value' => max(2, min(19.5, round($mark * 2) / 2))]];
                })->all());
            }
        }

        $this->travel($term->ends_on->format('Y-m-d').' 17:00');

        foreach ($classSubjects as $classSubject) {
            app(SaveTermComments::class)->handle($term, $classSubject, $this->subjectComments($classSubject, $enrollments, $trend), $this->admin);
        }

        app(ManageTerm::class)->lock($term->fresh(), $this->admin);
        app(SaveTermComments::class)->handle($term->fresh(), null, $this->councilComments($term, $schoolClass, $enrollments), $this->admin);
        app(ManageTerm::class)->publish($term->fresh(), $this->admin);
    }

    /**
     * Appels du trimestre : quelques absences et un retard ; une absence
     * pour maladie justifiée.
     *
     * @param  Collection<int, array{enrollment: Enrollment, level: float}>  $enrollments
     */
    private function attendance(Term $term, SchoolClass $schoolClass, Collection $enrollments): void
    {
        $slot = AttendanceSlot::query()->orderBy('starts_at')->firstOrFail();
        $ids = $enrollments->map(fn (array $student): int => $student['enrollment']->id)->values();
        $days = [
            [3, [$ids[2] => ['status' => 'absent'], $ids[6] => ['status' => 'absent']]],
            [6, [$ids[6] => ['status' => 'late', 'late_minutes' => 20], $ids[8] => ['status' => 'absent']]],
            [9, [$ids[6] => ['status' => 'absent'], $ids[1] => ['status' => 'absent']]],
        ];

        foreach ($days as [$week, $entries]) {
            $date = $this->weekday($term->starts_on->copy()->addWeeks($week));
            $this->travel($date->format('Y-m-d').' 09:00');
            app(TakeAttendance::class)->handle($schoolClass, $date, $slot, $entries, $this->admin);

            if ($week === 9) {
                app(JustifyAbsences::class)->handle(Enrollment::query()->findOrFail($ids[1]), $date, $date, JustificationReason::Illness, 'Certificat médical', $this->admin);
            }
        }
    }

    /**
     * Appréciation de l'enseignant de la matière, selon le niveau de l'élève.
     *
     * @param  Collection<int, array{enrollment: Enrollment, level: float}>  $enrollments
     * @return array<int, string>
     */
    private function subjectComments(ClassSubject $classSubject, Collection $enrollments, float $trend): array
    {
        $difficulty = self::SUBJECTS[$classSubject->subject->code][2] ?? 0.0;

        return $enrollments->mapWithKeys(fn (array $student): array => [$student['enrollment']->id => match (true) {
            $student['level'] + $difficulty + $trend >= 15.5 => 'Très bien',
            $student['level'] + $difficulty + $trend >= 13 => 'Bien',
            $student['level'] + $difficulty + $trend >= 11.5 => 'Assez bien',
            $student['level'] + $difficulty + $trend >= 10 => 'Passable',
            default => 'Insuffisant, doit travailler',
        }])->all();
    }

    /**
     * Appréciation du conseil selon la moyenne provisoire de l'élève.
     *
     * @param  Collection<int, array{enrollment: Enrollment, level: float}>  $enrollments
     * @return array<int, string>
     */
    private function councilComments(Term $term, SchoolClass $schoolClass, Collection $enrollments): array
    {
        $results = app(ComputeClassTermResults::class)->handle($term, $schoolClass);

        return $enrollments->mapWithKeys(function (array $student) use ($results): array {
            $average = $results->average($student['enrollment']->id) ?? 0;

            return [$student['enrollment']->id => match (true) {
                $average >= 16 => 'Excellent travail. Félicitations du conseil.',
                $average >= 14 => 'Très bon trimestre. Continuez ainsi.',
                $average >= 12 => 'Bon travail, des efforts réguliers.',
                $average >= 10 => 'Résultats justes. Peut mieux faire.',
                default => 'Résultats insuffisants. Travail et assiduité à améliorer.',
            }];
        })->all();
    }

    /**
     * Décisions proposées d'après la moyenne annuelle (passage en 5e ou
     * redoublement).
     *
     * @param  Collection<int, array{enrollment: Enrollment, level: float}>  $enrollments
     */
    private function decisions(SchoolClass $schoolClass, Collection $enrollments): void
    {
        $settings = GradingSetting::current();
        $schoolClass->load('schoolYear');

        app(YearEndDecisions::class)->save($schoolClass, $enrollments->mapWithKeys(function (array $student) use ($settings): array {
            $enrollment = $student['enrollment']->fresh(['schoolClass.level']);

            return [$enrollment->id => YearEndDecisions::propose($enrollment, AnnualResult::query()->firstWhere('enrollment_id', $enrollment->id), $settings)?->value];
        })->all(), $this->admin);
    }

    private function travel(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, config('app.timezone')));
    }

    /**
     * Le jour ouvré le plus proche (lundi si le jour tombe un week-end).
     */
    private function weekday(Carbon $date): Carbon
    {
        return $date->isWeekend() ? $date->copy()->next(Carbon::MONDAY) : $date->copy();
    }

    /**
     * Variation de note reproductible, entre -2 et +2.
     */
    private function noise(): float
    {
        $this->seed = ($this->seed * 1103515245 + 12345) % 2147483648;

        return ($this->seed / 2147483648) * 4 - 2;
    }
}
