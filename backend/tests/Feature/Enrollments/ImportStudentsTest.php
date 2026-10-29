<?php

use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\ImportStudents;
use App\Modules\Enrollments\Import\StudentImportColumns;
use App\Modules\Enrollments\Import\StudentImportTemplate;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    Carbon::setTestNow('2027-03-15 10:00:00');

    [$this->tenant] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->year = SchoolYear::query()->open()->sole();
    foreach (['6e A' => '6E', '2nde C' => '2ND'] as $name => $level) {
        SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', $level)->id, 'name' => $name, 'capacity' => 3]);
    }
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @param  list<list<mixed>>  $rows
 */
function xlsxFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(array_values(StudentImportColumns::COLUMNS)));
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return $path;
}

function analyze(string $path, string $extension = 'xlsx')
{
    return app(ImportStudents::class)->analyze($path, $extension, SchoolYear::query()->open()->sole());
}

it('importe les élèves et leurs responsables d\'un fichier Excel', function () {
    $path = xlsxFile([
        ['Mabiala', 'Grâce', 'F', '12/05/2014', 'Brazzaville', '6e A', 'Okemba', 'Prisca', '06 123 45 67', 'Mère', ''],
        ['Nkounkou', 'Arsène', 'Garçon', new DateTimeImmutable('2010-01-20'), 'Dolisie', '2NDE C', 'Okemba', 'Prisca', '061234567', 'mere', 'Collège Javouhey'],
    ]);

    $report = analyze($path);

    expect($report->errorMessages())->toBe([])
        ->and(app(ImportStudents::class)->import($report))->toBe(2)
        ->and(Student::orderBy('registration_number')->pluck('registration_number')->all())->toBe(['26-0001', '26-0002'])
        ->and(Student::firstWhere('last_name', 'NKOUNKOU')->birth_date->toDateString())->toBe('2010-01-20')
        ->and(Guardian::count())->toBe(1);
});

it('lit un CSV enregistré par Excel en français (points-virgules)', function () {
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, implode(';', StudentImportColumns::COLUMNS)."\nMabiala;Grâce;F;12/05/2014;Brazzaville;6e A;;;;;\n");

    expect(analyze($path, 'csv')->isValid())->toBeTrue();
});

it('liste toutes les erreurs avec leur numéro de ligne et n\'importe rien', function () {
    $path = xlsxFile([
        ['Mabiala', 'Grâce', 'F', '12/05/2014', 'Brazzaville', '6e A', '', '', '', '', ''],
        ['', 'Arsène', 'X', '31/02/2012', '', '5e Z', '', '', '07 12', '', ''],
        ['Mabiala', 'grâce', 'F', '12/05/2014', '', '6e A', '', '', '', '', ''],
    ]);

    $report = analyze($path);

    expect($report->isValid())->toBeFalse()
        ->and($report->errorMessages())->toBe([
            'Ligne 3 : nom manquant.',
            'Ligne 3 : sexe invalide (F ou M).',
            'Ligne 3 : date de naissance invalide (format JJ/MM/AAAA).',
            'Ligne 3 : classe « 5e Z » inconnue pour cette année.',
            'Ligne 3 : nom du responsable manquant.',
            'Ligne 3 : téléphone du responsable invalide (07 12).',
            'Ligne 4 : même élève qu\'à la ligne 2.',
        ]);

    app(ImportStudents::class)->import($report);
})->throws(BusinessRuleViolation::class);

it('refuse les élèves déjà connus et les classes trop petites', function () {
    Student::factory()->create(['last_name' => 'MABIALA', 'first_names' => 'Grâce', 'birth_date' => '2014-05-12']);

    $rows = [['Mabiala', 'Grâce', 'F', '12/05/2014', '', '6e A', '', '', '', '', '']];
    foreach (range(1, 4) as $index) {
        $rows[] = ["Élève{$index}", 'Test', 'M', '01/01/2013', '', '2nde C', '', '', '', '', ''];
    }

    expect(analyze(xlsxFile($rows))->errorMessages())->toBe([
        'Ligne 2 : MABIALA Grâce existe déjà dans l\'école : utilisez la réinscription.',
        'La classe 2nde C n\'a que 3 place(s) libre(s) pour 4 élève(s) à importer.',
    ]);
});

it('exige les colonnes obligatoires', function () {
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, "Nom;Prénoms\nMabiala;Grâce\n");

    analyze($path, 'csv');
})->throws(BusinessRuleViolation::class, 'Colonnes manquantes');

it('produit un modèle relu sans erreur de colonnes', function () {
    $path = tempnam(sys_get_temp_dir(), 'modele').'.xlsx';
    app(StudentImportTemplate::class)->writeTo($path);

    expect(analyze($path)->isValid())->toBeTrue();
});
