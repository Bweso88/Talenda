<?php

namespace App\Filament\School\Resources\Rooms\Pages;

use App\Filament\School\Resources\Rooms\RoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRooms extends ManageRecords
{
    protected static string $resource = RoomResource::class;

    protected ?string $subheading = 'Enregistrez les salles de chaque site : elles se choisissent ensuite sur les classes.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
