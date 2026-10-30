<?php

namespace App\Filament\Platform\Resources\Tenants\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('École')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Identifiant')
                    ->searchable(),
                TextColumn::make('country_code')
                    ->label('Pays'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('memberships_count')
                    ->label('Comptes')
                    ->counts('memberships'),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
