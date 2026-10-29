<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Filament\School\Resources\Enrollments\EnrollmentActions;
use App\Modules\Enrollments\Models\Enrollment;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scolarité de l'élève dans l'école, année par année.
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Scolarité';

    protected static ?string $modelLabel = 'inscription';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['schoolYear', 'schoolClass']))
            ->defaultSort('enrolled_on', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('schoolYear.name')->label('Année')->weight('bold'),
                TextColumn::make('schoolClass.name')->label('Classe'),
                TextColumn::make('type')->label('Type'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('enrolled_on')->label('Inscrit le')->date('d/m/Y'),
                TextColumn::make('missing_documents')
                    ->label('Pièces manquantes')
                    ->getStateUsing(fn (Enrollment $record): string => implode(', ', $record->missingDocuments()) ?: 'Aucune')
                    ->wrap(),
                TextColumn::make('left_on')
                    ->label('Départ')
                    ->formatStateUsing(fn (Enrollment $record): string => $record->left_on?->format('d/m/Y')
                        .($record->destination_school ? ' → '.$record->destination_school : ''))
                    ->placeholder('—'),
                TextColumn::make('decision')->label('Décision')->placeholder('—'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EnrollmentActions::schoolCertificate(),
                    EnrollmentActions::confirm(),
                    EnrollmentActions::documents(),
                    EnrollmentActions::changeClass(),
                    EnrollmentActions::departure(),
                ]),
            ]);
    }
}
