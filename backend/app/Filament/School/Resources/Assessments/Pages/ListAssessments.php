<?php

namespace App\Filament\School\Resources\Assessments\Pages;

use App\Filament\School\Resources\Assessments\AssessmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssessments extends ListRecords
{
    protected static string $resource = AssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvelle évaluation'),
        ];
    }
}
