<?php

namespace App\Filament\School\Resources\StaffMembers\Tables;

use App\Filament\School\Resources\StaffMembers\StaffMemberActions;
use App\Modules\Academic\Enums\StaffPosition;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StaffMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nom')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('position')->label('Fonction')->badge(),
                TextColumn::make('phone')->label('Téléphone')->placeholder('—'),
                TextColumn::make('employee_number')->label('Matricule')->placeholder('—')->toggleable(),
                IconColumn::make('membership_id')
                    ->label('Accès')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->hasAccess()),
                IconColumn::make('is_active')->label('En poste')->boolean(),
            ])
            ->filters([
                SelectFilter::make('position')->label('Fonction')->options(StaffPosition::class),
                TernaryFilter::make('is_active')->label('En poste')->default(true),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    StaffMemberActions::grantAccess(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
