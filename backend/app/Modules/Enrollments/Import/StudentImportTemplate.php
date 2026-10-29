<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Modèle Excel à remplir par l'école (en-têtes + une ligne d'exemple).
 */
final class StudentImportTemplate
{
    public function writeTo(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues(array_values(StudentImportColumns::COLUMNS), (new Style)->setFontBold()));
        $writer->addRow(Row::fromValues([
            'MABIALA', 'Grâce Divine', 'F', '12/05/2014', 'Brazzaville', '6e A',
            'OKEMBA', 'Prisca', '06 123 45 67', 'Mère', 'École Saint-Esprit',
        ]));

        $writer->close();
    }
}
