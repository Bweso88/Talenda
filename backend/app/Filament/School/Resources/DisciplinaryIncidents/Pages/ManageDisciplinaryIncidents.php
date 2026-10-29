<?php

namespace App\Filament\School\Resources\DisciplinaryIncidents\Pages;

use App\Filament\School\Resources\DisciplinaryIncidents\DisciplinaryIncidentResource;
use App\Modules\SchoolLife\Models\DisciplinaryIncident;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDisciplinaryIncidents extends ManageRecords
{
    protected static string $resource = DisciplinaryIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nouvel incident')
                ->using(fn (array $data): DisciplinaryIncident => DisciplinaryIncidentResource::save($data)),
        ];
    }
}
