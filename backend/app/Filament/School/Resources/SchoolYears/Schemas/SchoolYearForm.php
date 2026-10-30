<?php

namespace App\Filament\School\Resources\SchoolYears\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SchoolYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->components([
                        TextInput::make('start_year')
                            ->label('Année de la rentrée')
                            ->helperText('Ex. 2027 pour l\'année scolaire 2027-2028. Les trimestres sont créés automatiquement.')
                            ->numeric()
                            ->integer()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->default(fn (): int => now()->year)
                            ->required()
                            ->visibleOn('create'),
                        TextInput::make('name')
                            ->label('Année scolaire')
                            ->disabled()
                            ->visibleOn('edit'),
                        DatePicker::make('starts_on')
                            ->label('Début')
                            ->required()
                            ->visibleOn('edit'),
                        DatePicker::make('ends_on')
                            ->label('Fin')
                            ->required()
                            ->after('starts_on')
                            ->visibleOn('edit'),
                    ]),
            ]);
    }
}
