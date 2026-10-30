<?php

namespace App\Filament\School\Resources\FeeTypes\Pages;

use App\Filament\School\Resources\FeeTypes\FeeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFeeTypes extends ManageRecords
{
    protected static string $resource = FeeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
