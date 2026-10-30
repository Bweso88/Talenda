<?php

namespace App\Filament\School\Resources\Streams\Pages;

use App\Filament\School\Resources\Streams\StreamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStreams extends ManageRecords
{
    protected static string $resource = StreamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
