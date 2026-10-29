<?php

namespace App\Filament\School\Resources\Students\Pages;

use App\Filament\School\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Importer depuis Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->url(fn (): string => StudentResource::getUrl('import'))
                ->visible(fn (): bool => StudentResource::canCreate()),
            CreateAction::make()->label('Nouvelle inscription'),
        ];
    }
}
