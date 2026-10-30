<?php

namespace App\Filament\School\Resources\AttendanceSlots\Pages;

use App\Filament\School\Resources\AttendanceSlots\AttendanceSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceSlots extends ManageRecords
{
    protected static string $resource = AttendanceSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
