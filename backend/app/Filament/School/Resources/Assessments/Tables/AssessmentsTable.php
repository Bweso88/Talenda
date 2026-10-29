<?php

namespace App\Filament\School\Resources\Assessments\Tables;

use App\Filament\School\Resources\Assessments\AssessmentResource;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Models\Assessment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssessmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['classSubject.schoolClass', 'classSubject.subject', 'term', 'assessmentType'])
                ->withCount('scores'))
            ->defaultSort('held_on', 'desc')
            ->recordUrl(fn (Assessment $record): string => AssessmentResource::getUrl('scores', ['record' => $record]))
            ->columns([
                TextColumn::make('held_on')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('classSubject.schoolClass.name')->label('Classe'),
                TextColumn::make('classSubject.subject.name')->label('Matière'),
                TextColumn::make('title')->label('Intitulé')->weight('bold')->searchable(),
                TextColumn::make('assessmentType.name')->label('Type')->badge()->color('gray'),
                TextColumn::make('max_score')->label('Sur')->formatStateUsing(fn ($state): string => rtrim(rtrim((string) $state, '0'), '.')),
                TextColumn::make('scores_count')->label('Notes saisies'),
                TextColumn::make('term.name')->label('Période'),
            ])
            ->filters([
                SelectFilter::make('term_id')
                    ->label('Période')
                    ->options(fn (): array => Term::query()
                        ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                        ->orderBy('position')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->recordActions([
                Action::make('scores')
                    ->label('Saisir les notes')
                    ->icon(Heroicon::OutlinedPencil)
                    ->url(fn (Assessment $record): string => AssessmentResource::getUrl('scores', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
