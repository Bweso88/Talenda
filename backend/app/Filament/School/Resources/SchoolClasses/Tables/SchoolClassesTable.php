<?php

namespace App\Filament\School\Resources\SchoolClasses\Tables;

use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolYear;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchoolClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['level', 'stream', 'headTeacher', 'schoolYear', 'site', 'room'])
                ->withSum('classSubjects as total_coefficient', 'coefficient'))
            ->defaultSort(fn (Builder $query) => $query
                ->orderBy(
                    Level::query()->select('position')->whereColumn('levels.id', 'school_classes.level_id'),
                )
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')->label('Classe')->weight('bold')->searchable(),
                TextColumn::make('level.name')->label('Niveau'),
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('room.name')->label('Salle')->placeholder('—'),
                TextColumn::make('stream.code')->label('Série')->placeholder('—'),
                TextColumn::make('headTeacher.full_name')->label('Professeur principal')->placeholder('—'),
                TextColumn::make('class_subjects_count')->label('Matières')->counts('classSubjects'),
                TextColumn::make('total_coefficient')->label('Total des coefficients')->numeric(decimalPlaces: 1)->placeholder('—'),
                TextColumn::make('capacity')->label('Effectif max.')->placeholder('—'),
                TextColumn::make('schoolYear.name')->label('Année')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school_year_id')
                    ->label('Année scolaire')
                    ->relationship('schoolYear', 'name', fn (Builder $query) => $query->orderByDesc('starts_on'))
                    ->default(fn (): ?int => SchoolYear::query()->open()->value('id')),
                SelectFilter::make('level_id')
                    ->label('Niveau')
                    ->relationship('level', 'name', fn (Builder $query) => $query->orderBy('position')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
