<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Import;

use App\Modules\Core\Exceptions\BusinessRuleViolation;
use DateTimeInterface;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lit la première feuille d'un fichier .xlsx ou .csv et renvoie ses lignes
 * indexées par les clés de StudentImportColumns, avec leur numéro de ligne
 * dans le fichier (pour des messages d'erreur exploitables).
 */
final class SpreadsheetReader
{
    /**
     * @return array<int, array<string, mixed>> numéro de ligne => valeurs
     */
    public function rows(string $path, string $extension): array
    {
        $reader = $this->readerFor($path, strtolower($extension));
        $reader->open($path);

        try {
            $columnKeys = null;
            $rows = [];
            $lineNumber = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $lineNumber++;
                    $cells = $row->toArray();

                    if ($columnKeys === null) {
                        $columnKeys = array_map(
                            fn (mixed $header): ?string => StudentImportColumns::keyForHeader((string) $header),
                            $cells,
                        );
                        $this->ensureRequiredColumns($columnKeys);

                        continue;
                    }

                    if ($this->isBlank($cells)) {
                        continue;
                    }

                    $values = [];

                    foreach ($columnKeys as $index => $key) {
                        if ($key !== null) {
                            $values[$key] = $cells[$index] ?? null;
                        }
                    }

                    $rows[$lineNumber] = $values;
                }

                break;
            }

            return $rows;
        } finally {
            $reader->close();
        }
    }

    /**
     * @return XlsxReader|CsvReader
     */
    private function readerFor(string $path, string $extension): ReaderInterface
    {
        return match ($extension) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader($this->csvOptions($path)),
            default => throw new BusinessRuleViolation('Format non pris en charge : utilisez un fichier Excel (.xlsx) ou CSV.'),
        };
    }

    /**
     * Excel en français enregistre les CSV avec des points-virgules.
     */
    private function csvOptions(string $path): CsvOptions
    {
        $firstLine = (string) fgets(fopen($path, 'r') ?: throw new BusinessRuleViolation('Fichier illisible.'));

        $options = new CsvOptions;
        $options->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        return $options;
    }

    /**
     * @param  array<int, string|null>  $columnKeys
     */
    private function ensureRequiredColumns(array $columnKeys): void
    {
        $missing = array_diff(StudentImportColumns::REQUIRED, array_filter($columnKeys));

        if ($missing !== []) {
            $labels = array_map(fn (string $key): string => '« '.StudentImportColumns::COLUMNS[$key].' »', $missing);

            throw new BusinessRuleViolation('Colonnes manquantes dans le fichier : '.implode(', ', $labels).'. Utilisez le modèle fourni.');
        }
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) ($cell instanceof DateTimeInterface ? 'date' : $cell)) !== '') {
                return false;
            }
        }

        return true;
    }
}
