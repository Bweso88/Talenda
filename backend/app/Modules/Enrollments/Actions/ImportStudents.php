<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Actions;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Enrollments\Enums\GuardianRelationship;
use App\Modules\Enrollments\Import\SpreadsheetReader;
use App\Modules\Enrollments\Import\StudentImportReport;
use App\Modules\Enrollments\Models\Student;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import des élèves depuis Excel, pour la mise en place d'une école
 * (docs/PRODUCT_ARCHITECTURE.md §16, étape 2).
 *
 * En deux temps : analyse complète du fichier (toutes les erreurs, ligne
 * par ligne), puis import **tout ou rien** dans une transaction. Une école
 * ne se retrouve jamais avec la moitié de ses élèves importés.
 */
final class ImportStudents
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly EnrollNewStudent $enrollNewStudent,
        private readonly RecordAudit $recordAudit,
    ) {}

    public function analyze(string $path, string $extension, SchoolYear $schoolYear): StudentImportReport
    {
        if ($schoolYear->isClosed()) {
            throw new BusinessRuleViolation("L'année {$schoolYear->name} est clôturée.");
        }

        $classes = $schoolYear->schoolClasses()->withCount(['enrollments as occupied_seats' => fn ($query) => $query->active()])->get();
        $classesByName = $classes->keyBy(fn (SchoolClass $schoolClass): string => $this->normalizeClassName($schoolClass->name));

        $rows = [];
        $errors = [];
        $seenStudents = [];
        $seatsRequested = [];

        foreach ($this->reader->rows($path, $extension) as $line => $values) {
            $lineErrors = [];
            $row = $this->parseRow($values, $classesByName, $lineErrors);

            if ($row !== null) {
                $identity = $row['student']['last_name'].'|'.mb_strtolower($row['student']['first_names']).'|'.$row['student']['birth_date'];

                if (isset($seenStudents[$identity])) {
                    $lineErrors[] = "même élève qu'à la ligne {$seenStudents[$identity]}.";
                } elseif ($this->alreadyKnown($row['student'])) {
                    $lineErrors[] = "{$row['student']['last_name']} {$row['student']['first_names']} existe déjà dans l'école : utilisez la réinscription.";
                }

                $seenStudents[$identity] ??= $line;
                $seatsRequested[$row['school_class_id']] = ($seatsRequested[$row['school_class_id']] ?? 0) + 1;
            }

            if ($lineErrors !== []) {
                $errors[$line] = $lineErrors;
            } elseif ($row !== null) {
                $rows[$line] = $row;
            }
        }

        foreach ($this->capacityErrors($classes, $seatsRequested) as $message) {
            $errors[0][] = $message;
        }

        if ($rows === [] && $errors === []) {
            $errors[0][] = 'Le fichier ne contient aucun élève.';
        }

        return new StudentImportReport($rows, $errors);
    }

    /**
     * @return int nombre d'élèves importés
     */
    public function import(StudentImportReport $report, ?Model $actor = null): int
    {
        if (! $report->isValid()) {
            throw new BusinessRuleViolation('Le fichier contient des erreurs : corrigez-les avant d\'importer.');
        }

        return DB::transaction(function () use ($report, $actor): int {
            $classes = SchoolClass::query()->with('schoolYear')->findMany(array_column($report->rows, 'school_class_id'))->keyBy('id');

            foreach ($report->rows as $row) {
                $this->enrollNewStudent->handle($row['student'], $row['guardians'], $classes[$row['school_class_id']], actor: $actor);
            }

            $this->recordAudit->handle('enrollment.imported', actor: $actor, metadata: ['count' => count($report->rows)]);

            return count($report->rows);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  SupportCollection<string, SchoolClass>  $classesByName
     * @param  list<string>  $errors
     * @return array{student: array{last_name: string, first_names: string, gender: string, birth_date: string, birth_place: string|null, previous_school: string|null}, guardians: list<array{last_name: string, first_names: string|null, phone: string|null, relationship: string}>, school_class_id: int}|null
     */
    private function parseRow(array $values, SupportCollection $classesByName, array &$errors): ?array
    {
        $text = fn (string $key): ?string => blank($values[$key] ?? null) || $values[$key] instanceof DateTimeInterface
            ? null
            : trim((string) $values[$key]);

        $lastName = $text('last_name');
        $firstNames = $text('first_names');

        if ($lastName === null) {
            $errors[] = 'nom manquant.';
        }

        if ($firstNames === null) {
            $errors[] = 'prénoms manquants.';
        }

        $gender = $this->parseGender($text('gender'));

        if ($gender === null) {
            $errors[] = 'sexe invalide (F ou M).';
        }

        $birthDate = $this->parseDate($values['birth_date'] ?? null);

        if ($birthDate === null) {
            $errors[] = 'date de naissance invalide (format JJ/MM/AAAA).';
        } elseif ($birthDate->isFuture() || $birthDate->lt(now()->subYears(30))) {
            $errors[] = 'date de naissance improbable ('.$birthDate->format('d/m/Y').').';
        }

        $className = $text('class');
        $schoolClass = $className === null ? null : $classesByName->get($this->normalizeClassName($className));

        if ($schoolClass === null) {
            $errors[] = $className === null ? 'classe manquante.' : "classe « {$className} » inconnue pour cette année.";
        }

        $guardians = $this->parseGuardian($text, $errors);

        if ($errors !== [] || $lastName === null || $firstNames === null || $gender === null || $birthDate === null || $schoolClass === null) {
            return null;
        }

        return [
            'student' => [
                'last_name' => mb_strtoupper($lastName),
                'first_names' => $firstNames,
                'gender' => $gender,
                'birth_date' => $birthDate->toDateString(),
                'birth_place' => $text('birth_place'),
                'previous_school' => $text('previous_school'),
            ],
            'guardians' => $guardians,
            'school_class_id' => $schoolClass->id,
        ];
    }

    /**
     * @param  callable(string): ?string  $text
     * @param  list<string>  $errors
     * @return list<array{last_name: string, first_names: string|null, phone: string|null, relationship: string}>
     */
    private function parseGuardian(callable $text, array &$errors): array
    {
        $lastName = $text('guardian_last_name');
        $phoneInput = $text('guardian_phone');

        if ($lastName === null && $phoneInput === null) {
            return [];
        }

        if ($lastName === null) {
            $errors[] = 'nom du responsable manquant.';
        }

        $phone = $phoneInput === null ? null : PhoneNumber::normalize($phoneInput);

        if ($phoneInput !== null && $phone === null) {
            $errors[] = "téléphone du responsable invalide ({$phoneInput}).";
        }

        $relationship = $this->parseRelationship($text('guardian_relationship'));

        if ($lastName === null) {
            return [];
        }

        return [[
            'last_name' => $lastName,
            'first_names' => $text('guardian_first_names'),
            'phone' => $phone,
            'relationship' => $relationship->value,
        ]];
    }

    private function parseGender(?string $value): ?string
    {
        return match (Str::lower(Str::ascii((string) $value))) {
            'f', 'fille', 'feminin', 'femme' => 'F',
            'm', 'g', 'garcon', 'masculin', 'homme' => 'M',
            default => null,
        };
    }

    private function parseRelationship(?string $value): GuardianRelationship
    {
        return match (Str::lower(Str::ascii((string) $value))) {
            'pere', 'papa' => GuardianRelationship::Father,
            'mere', 'maman' => GuardianRelationship::Mother,
            'tuteur', 'tutrice' => GuardianRelationship::Guardian,
            default => GuardianRelationship::Other,
        };
    }

    /**
     * Accepte une vraie date Excel, un nombre de série Excel, JJ/MM/AAAA
     * ou AAAA-MM-JJ.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_int($value) || is_float($value) || (is_string($value) && ctype_digit($value))) {
            // Numéro de série Excel : jours depuis le 30/12/1899.
            return Carbon::create(1899, 12, 30)->addDays((int) $value);
        }

        $value = trim((string) $value);

        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($date !== null && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param  array{last_name: string, first_names: string, birth_date: string}  $student
     */
    private function alreadyKnown(array $student): bool
    {
        return Student::query()
            ->where('last_name', $student['last_name'])
            ->whereRaw('lower(first_names) = ?', [mb_strtolower($student['first_names'])])
            ->whereDate('birth_date', $student['birth_date'])
            ->exists();
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  array<int, int>  $seatsRequested
     * @return list<string>
     */
    private function capacityErrors(Collection $classes, array $seatsRequested): array
    {
        $messages = [];

        foreach ($classes as $schoolClass) {
            $requested = $seatsRequested[$schoolClass->id] ?? 0;
            $occupied = (int) $schoolClass->getAttribute('occupied_seats');

            if ($schoolClass->capacity !== null && $requested > 0 && $occupied + $requested > $schoolClass->capacity) {
                $messages[] = "la classe {$schoolClass->name} n'a que ".max(0, $schoolClass->capacity - $occupied)." place(s) libre(s) pour {$requested} élève(s) à importer.";
            }
        }

        return $messages;
    }

    private function normalizeClassName(string $name): string
    {
        return (string) preg_replace('/\s+/', '', Str::lower(Str::ascii($name)));
    }
}
