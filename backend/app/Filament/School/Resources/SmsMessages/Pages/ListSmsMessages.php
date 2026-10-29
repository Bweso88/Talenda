<?php

namespace App\Filament\School\Resources\SmsMessages\Pages;

use App\Filament\School\Resources\SmsMessages\SmsMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListSmsMessages extends ListRecords
{
    protected static string $resource = SmsMessageResource::class;
}
