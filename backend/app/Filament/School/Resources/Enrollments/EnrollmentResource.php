<?php

namespace App\Filament\School\Resources\Enrollments;

use App\Filament\School\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\School\Resources\Enrollments\Tables\EnrollmentsTable;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Modules\Enrollments\Models\Enrollment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Inscriptions par année et par classe : listes de classe, réinscriptions
 * groupées, cartes scolaires. Les nouvelles inscriptions se font depuis
 * « Élèves ».
 */
class EnrollmentResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::STUDENTS;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'inscriptions';

    protected static ?string $modelLabel = 'inscription';

    protected static ?string $navigationLabel = 'Listes de classe';

    public static function table(Table $table): Table
    {
        return EnrollmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnrollments::route('/'),
        ];
    }
}
