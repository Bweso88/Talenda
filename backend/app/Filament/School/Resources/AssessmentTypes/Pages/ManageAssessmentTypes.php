<?php

namespace App\Filament\School\Resources\AssessmentTypes\Pages;

use App\Filament\School\Resources\AssessmentTypes\AssessmentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAssessmentTypes extends ManageRecords
{
    protected static string $resource = AssessmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
