<?php

namespace App\Filament\School\Resources\CashRegisters\Pages;

use App\Filament\School\Resources\CashRegisters\CashRegisterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCashRegisters extends ManageRecords
{
    protected static string $resource = CashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
