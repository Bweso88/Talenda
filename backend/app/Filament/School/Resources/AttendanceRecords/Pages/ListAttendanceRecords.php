<?php

namespace App\Filament\School\Resources\AttendanceRecords\Pages;

use App\Filament\School\Resources\AttendanceRecords\AttendanceRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceRecords extends ListRecords
{
    protected static string $resource = AttendanceRecordResource::class;
}
