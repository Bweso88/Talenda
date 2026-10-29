<?php

namespace App\Filament\School\Resources\Enrollments\Tables;

use App\Filament\School\Resources\Enrollments\EnrollmentActions;
use App\Filament\School\Resources\Students\StudentResource;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Enrollments\Actions\ReEnrollStudent;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Student;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student', 'schoolClass.level.nextLevel', 'schoolYear']))
            ->defaultSort(fn (Builder $query) => $query->orderBy(
                Student::query()->select('last_name')->whereColumn('students.id', 'enrollments.student_id'),
            ))
            ->recordUrl(fn (Enrollment $record): string => StudentResource::getUrl('edit', ['record' => $record->student_id]))
            ->columns([
                TextColumn::make('student.registration_number')->label('Matricule')->searchable(),
                TextColumn::make('student.full_name')
                    ->label('Élève')
                    ->weight('bold')
                    ->searchable(['last_name', 'first_names']),
                TextColumn::make('student.gender')->label('Sexe')->badge()->color('gray'),
                TextColumn::make('schoolClass.name')->label('Classe'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('type')->label('Type')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('decision')->label('Décision de fin d\'année')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('suggested_level')
                    ->label('Niveau proposé l\'an prochain')
                    ->getStateUsing(fn (Enrollment $record): ?string => ReEnrollStudent::suggestedLevel($record)?->name)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('documents')
                    ->label('Dossier')
                    ->getStateUsing(fn (Enrollment $record): string => count($record->missingDocuments()) === 0 ? 'Complet' : count($record->missingDocuments()).' pièce(s) manquante(s)')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Complet' ? 'success' : 'warning'),
                TextColumn::make('schoolYear.name')->label('Année')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school_year_id')
                    ->label('Année scolaire')
                    ->relationship('schoolYear', 'name', fn (Builder $query) => $query->orderByDesc('starts_on'))
                    ->default(fn (): ?int => EnrollmentActions::openYearId()),
                SelectFilter::make('school_class_id')
                    ->label('Classe')
                    ->options(fn (): array => SchoolClass::query()
                        ->with('schoolYear')
                        ->get()
                        ->sortBy(['schoolYear.starts_on', 'name'])
                        ->groupBy('schoolYear.name')
                        ->map(fn ($classes) => $classes->pluck('name', 'id')->all())
                        ->all()),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->multiple()
                    ->options(EnrollmentStatus::class)
                    ->default(array_map(fn (EnrollmentStatus $status): string => $status->value, EnrollmentStatus::active())),
            ])
            ->recordActions([
                ActionGroup::make([
                    EnrollmentActions::schoolCertificate(),
                    EnrollmentActions::confirm(),
                    EnrollmentActions::documents(),
                    EnrollmentActions::changeClass(),
                    EnrollmentActions::departure(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    EnrollmentActions::reEnrollBulk(),
                    EnrollmentActions::studentCardsBulk(),
                ]),
            ]);
    }
}
