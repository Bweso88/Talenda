<?php

namespace App\Filament\School\Resources\Students\Tables;

use App\Filament\School\Resources\Enrollments\EnrollmentActions;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Student;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('activeEnrollment.schoolClass'))
            ->defaultSort('last_name')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('')
                    ->disk('local')
                    ->visibility('private')
                    ->circular()
                    ->defaultImageUrl(fn (Student $record): string => self::initialsAvatar($record)),
                TextColumn::make('registration_number')->label('Matricule')->searchable()->sortable(),
                TextColumn::make('full_name')
                    ->label('Élève')
                    ->weight('bold')
                    ->searchable(['last_name', 'first_names'])
                    ->sortable(['last_name', 'first_names']),
                TextColumn::make('activeEnrollment.schoolClass.name')->label('Classe')->placeholder('Pas inscrit cette année'),
                TextColumn::make('activeEnrollment.status')->label('Statut')->badge(),
                TextColumn::make('gender')->label('Sexe')->badge()->color('gray'),
                TextColumn::make('birth_date')->label('Né(e) le')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('class')
                    ->label('Classe (année ouverte)')
                    ->options(fn (): array => SchoolClass::query()
                        ->where('school_year_id', EnrollmentActions::openYearId())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $classId) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->whereIn('status', EnrollmentStatus::active())->where('school_class_id', $classId)),
                    )),
                TernaryFilter::make('enrolled')
                    ->label('Scolarisé cette année')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->whereIn('status', EnrollmentStatus::active())->where('school_year_id', EnrollmentActions::openYearId())),
                        false: fn (Builder $query) => $query->whereDoesntHave('enrollments', fn (Builder $enrollments) => $enrollments->whereIn('status', EnrollmentStatus::active())->where('school_year_id', EnrollmentActions::openYearId())),
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Fiche'),
            ]);
    }

    private static function initialsAvatar(Student $student): string
    {
        $initials = mb_substr($student->last_name, 0, 1).mb_substr($student->first_names, 0, 1);

        return 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="#e0e7ff"/><text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial" font-size="15" fill="#3730a3">'.htmlspecialchars($initials, ENT_XML1).'</text></svg>',
        );
    }
}
