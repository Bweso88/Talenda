<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academic\Enums\StaffPosition;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Stream;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Actions\ProvisionTenant;
use App\Modules\Core\Actions\SyncRolesAndPermissions;
use App\Modules\Core\Models\PlatformAdmin;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\Seeder;

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
                    'name' => 'Parent Démo',
                    'phone' => '+242063333333',
                    'email' => null,
                    'password' => 'talenda-demo',
                ]);
        }

        app(TenantContext::class)->run($brazzaville, function (): void {
            $this->seedDemoClasses();
            $this->seedDemoStudents();
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
        $mother = ['relationship' => 'mother', 'last_name' => 'Okemba', 'first_names' => 'Prisca', 'phone' => '+242065550001', 'is_financially_responsible' => true];

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
    }
}
