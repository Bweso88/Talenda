<?php

namespace App\Filament\School\Resources\SchoolClasses;

use App\Filament\School\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\School\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\School\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\School\Resources\SchoolClasses\RelationManagers\ClassSubjectsRelationManager;
use App\Filament\School\Resources\SchoolClasses\Schemas\SchoolClassForm;
use App\Filament\School\Resources\SchoolClasses\Tables\SchoolClassesTable;
use App\Filament\School\Support\NavigationGroups;
use App\Modules\Academic\Models\SchoolClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SchoolClassResource extends Resource
{
    protected static ?string $model = SchoolClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SCHOOL_YEAR;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'classes';

    protected static ?string $modelLabel = 'classe';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SchoolClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolClassesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ClassSubjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolClasses::route('/'),
            'create' => CreateSchoolClass::route('/nouveau'),
            'edit' => EditSchoolClass::route('/{record}/modifier'),
        ];
    }
}
