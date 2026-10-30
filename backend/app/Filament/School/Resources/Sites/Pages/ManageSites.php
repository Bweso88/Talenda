<?php

namespace App\Filament\School\Resources\Sites\Pages;

use App\Filament\School\Resources\Sites\SiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSites extends ManageRecords
{
    protected static string $resource = SiteResource::class;

    protected ?string $subheading = 'Une école à un seul site n\'a rien à régler ici. Avec plusieurs sites, chaque classe et chaque caisse est rattachée à son site : le caissier d\'un site ne voit que ses élèves.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
