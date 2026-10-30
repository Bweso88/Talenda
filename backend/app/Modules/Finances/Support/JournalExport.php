<?php

declare(strict_types=1);

namespace App\Modules\Finances\Support;

use App\Modules\Finances\Enums\CashRegisterKind;
use App\Modules\Finances\Models\JournalEntry;
use App\Modules\Finances\Models\JournalLine;
use Carbon\CarbonInterface;

/**
 * Journal de trésorerie au format attendu par un comptable (une ligne par
 * compte mouvementé), à importer dans son logiciel ou à ouvrir dans Excel.
 */
final class JournalExport
{
    /**
     * Code journal : CA (caisse), MM (mobile money), BQ (banque), OD (autres).
     */
    public static function journalCode(JournalEntry $entry): string
    {
        $kind = $entry->lines->first(fn (JournalLine $line): bool => $line->cashRegister !== null)?->cashRegister?->kind;

        return match ($kind) {
            CashRegisterKind::Cash => 'CA',
            CashRegisterKind::MobileMoney => 'MM',
            CashRegisterKind::Bank => 'BQ',
            null => 'OD',
        };
    }

    /**
     * @return list<list<string|int>>
     */
    public function rows(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = [['Date', 'Journal', 'N° pièce', 'Compte', 'Libellé du compte', 'Libellé de l\'écriture', 'Débit', 'Crédit']];

        JournalEntry::query()
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->with('lines.cashRegister')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get()
            ->each(function (JournalEntry $entry) use (&$rows): void {
                $journal = self::journalCode($entry);

                foreach ($entry->lines as $line) {
                    $rows[] = [$entry->entry_date->format('d/m/Y'), $journal, $entry->reference, $line->account_code, $line->account_label, $entry->label, $line->debit, $line->credit];
                }
            });

        return $rows;
    }

    /**
     * CSV lisible par Excel (UTF-8 avec BOM, séparateur point-virgule).
     */
    public function csv(CarbonInterface $from, CarbonInterface $to): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\u{FEFF}");

        foreach ($this->rows($from, $to) as $row) {
            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
