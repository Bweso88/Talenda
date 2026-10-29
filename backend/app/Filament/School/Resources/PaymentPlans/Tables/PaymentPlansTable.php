<?php

namespace App\Filament\School\Resources\PaymentPlans\Tables;

use App\Modules\Academic\Models\SchoolYear;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Plan')->weight('bold'),
                TextColumn::make('schoolYear.name')->label('Année'),
                IconColumn::make('is_default')->label('Par défaut')->boolean(),
                TextColumn::make('installments_count')->label('Échéances')->counts('installments'),
                TextColumn::make('invoices_count')->label('Familles')->counts('invoices'),
            ])
            ->filters([
                SelectFilter::make('school_year_id')
                    ->label('Année scolaire')
                    ->relationship('schoolYear', 'name', fn (Builder $query) => $query->orderByDesc('starts_on'))
                    ->default(fn (): ?int => SchoolYear::query()->open()->value('id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
