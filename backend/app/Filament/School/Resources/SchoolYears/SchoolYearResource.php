<?php

namespace App\Filament\School\Resources\SchoolYears;

use App\Filament\School\Resources\SchoolYears\Pages\CreateSchoolYear;
use App\Filament\School\Resources\SchoolYears\Pages\EditSchoolYear;
use App\Filament\School\Resources\SchoolYears\Pages\ListSchoolYears;
use App\Filament\School\Resources\SchoolYears\RelationManagers\TermsRelationManager;
use App\Filament\School\Resources\SchoolYears\Schemas\SchoolYearForm;
use App\Filament\School\Resources\SchoolYears\Tables\SchoolYearsTable;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Modules\Academic\Models\SchoolYear;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SchoolYearResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = SchoolYear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SCHOOL_YEAR;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'annees-scolaires';

    protected static ?string $modelLabel = 'année scolaire';

    protected static ?string $pluralModelLabel = 'années scolaires';

    protected static ?string $navigationLabel = 'Années scolaires';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SchoolYearForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolYearsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TermsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolYears::route('/'),
            'create' => CreateSchoolYear::route('/nouveau'),
            'edit' => EditSchoolYear::route('/{record}/modifier'),
        ];
    }
}
