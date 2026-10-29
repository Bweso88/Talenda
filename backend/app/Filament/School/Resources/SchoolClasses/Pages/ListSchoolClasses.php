<?php

namespace App\Filament\School\Resources\SchoolClasses\Pages;

use App\Filament\School\Resources\SchoolClasses\SchoolClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchoolClasses extends ListRecords
{
    protected static string $resource = SchoolClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
