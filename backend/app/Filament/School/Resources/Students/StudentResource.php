<?php

namespace App\Filament\School\Resources\Students;

use App\Filament\School\Resources\Students\Pages\CreateStudent;
use App\Filament\School\Resources\Students\Pages\EditStudent;
use App\Filament\School\Resources\Students\Pages\ImportStudents;
use App\Filament\School\Resources\Students\Pages\ListStudents;
use App\Filament\School\Resources\Students\RelationManagers\AttendanceRecordsRelationManager;
use App\Filament\School\Resources\Students\RelationManagers\DisciplinaryIncidentsRelationManager;
use App\Filament\School\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\School\Resources\Students\RelationManagers\GuardianLinksRelationManager;
use App\Filament\School\Resources\Students\RelationManagers\InvoicesRelationManager;
use App\Filament\School\Resources\Students\Schemas\StudentForm;
use App\Filament\School\Resources\Students\Tables\StudentsTable;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Modules\Enrollments\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StudentResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::STUDENTS;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'eleves';

    protected static ?string $modelLabel = 'élève';

    protected static ?string $recordTitleAttribute = 'last_name';

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['registration_number', 'last_name', 'first_names'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Student $record */
        return $record->full_name.' ('.$record->registration_number.')';
    }

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GuardianLinksRelationManager::class,
            EnrollmentsRelationManager::class,
            InvoicesRelationManager::class,
            AttendanceRecordsRelationManager::class,
            DisciplinaryIncidentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/nouvelle-inscription'),
            'import' => ImportStudents::route('/import'),
            'edit' => EditStudent::route('/{record}/modifier'),
        ];
    }
}
