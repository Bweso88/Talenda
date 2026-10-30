<?php

namespace App\Filament\School\Resources\StaffMembers\Pages;

use App\Filament\School\Resources\StaffMembers\StaffMemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaffMembers extends ListRecords
{
    protected static string $resource = StaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
