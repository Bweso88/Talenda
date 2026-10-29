<?php

namespace App\Filament\School\Resources\Enrollments\Pages;

use App\Filament\School\Resources\Enrollments\EnrollmentResource;
use Filament\Resources\Pages\ListRecords;

class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected static ?string $title = 'Listes de classe';

    protected ?string $subheading = 'Filtrez par année et par classe. Pour réinscrire : choisissez l\'année précédente et la classe, sélectionnez les élèves, puis « Réinscrire dans une classe… ».';
}
