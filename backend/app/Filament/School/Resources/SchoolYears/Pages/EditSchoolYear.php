<?php

namespace App\Filament\School\Resources\SchoolYears\Pages;

use App\Filament\School\Resources\SchoolYears\SchoolYearActions;
use App\Filament\School\Resources\SchoolYears\SchoolYearResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchoolYear extends EditRecord
{
    protected static string $resource = SchoolYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SchoolYearActions::open(),
            SchoolYearActions::close(),
            SchoolYearActions::prepareNext(),
            DeleteAction::make(),
        ];
    }
}
