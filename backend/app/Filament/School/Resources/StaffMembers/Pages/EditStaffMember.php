<?php

namespace App\Filament\School\Resources\StaffMembers\Pages;

use App\Filament\School\Resources\StaffMembers\StaffMemberActions;
use App\Filament\School\Resources\StaffMembers\StaffMemberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaffMember extends EditRecord
{
    protected static string $resource = StaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StaffMemberActions::grantAccess(),
            DeleteAction::make(),
        ];
    }
}
