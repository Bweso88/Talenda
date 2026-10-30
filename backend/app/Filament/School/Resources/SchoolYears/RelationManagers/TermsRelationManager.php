<?php

namespace App\Filament\School\Resources\SchoolYears\RelationManagers;

use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Périodes de l'année. Elles sont créées avec l'année (gabarit du pays) :
 * on ajuste ici leurs noms et leurs dates.
 */
class TermsRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';

    protected static ?string $title = 'Périodes';

    protected static ?string $modelLabel = 'période';

    public function isReadOnly(): bool
    {
        return ! (Filament::auth()->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(50),
                DatePicker::make('starts_on')
                    ->label('Début')
                    ->required(),
                DatePicker::make('ends_on')
                    ->label('Fin')
                    ->required()
                    ->after('starts_on'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('position')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Période'),
                TextColumn::make('starts_on')->label('Début')->date('d/m/Y'),
                TextColumn::make('ends_on')->label('Fin')->date('d/m/Y'),
                TextColumn::make('status')->label('Statut')->badge(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
