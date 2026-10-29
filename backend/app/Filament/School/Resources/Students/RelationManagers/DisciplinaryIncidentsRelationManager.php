<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Modules\SchoolLife\Models\DisciplinaryIncident;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Dossier de discipline de l'élève.
 */
class DisciplinaryIncidentsRelationManager extends RelationManager
{
    protected static string $relationship = 'disciplinaryIncidents';

    protected static ?string $title = 'Discipline';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Filament::auth()->user()?->can('discipline.manage') ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_on', 'desc')
            ->columns([
                TextColumn::make('occurred_on')->label('Date')->date('d/m/Y'),
                TextColumn::make('description')->label('Faits')->wrap(),
                TextColumn::make('sanction')->label('Sanction')->badge()
                    ->formatStateUsing(fn (DisciplinaryIncident $record): string => $record->sanction->getLabel()
                        .($record->suspension_days ? " ({$record->suspension_days} j)" : '')),
                IconColumn::make('parents_informed')->label('Parents informés')->boolean(),
            ]);
    }
}
