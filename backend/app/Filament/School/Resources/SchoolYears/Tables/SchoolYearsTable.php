<?php

namespace App\Filament\School\Resources\SchoolYears\Tables;

use App\Filament\School\Resources\SchoolYears\SchoolYearActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')->label('Année')->weight('bold'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('starts_on')->label('Début')->date('d/m/Y'),
                TextColumn::make('ends_on')->label('Fin')->date('d/m/Y'),
                TextColumn::make('terms_count')->label('Périodes')->counts('terms'),
                TextColumn::make('school_classes_count')->label('Classes')->counts('schoolClasses'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    SchoolYearActions::open(),
                    SchoolYearActions::close(),
                    SchoolYearActions::prepareNext(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
