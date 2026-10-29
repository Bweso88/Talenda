<?php

namespace App\Filament\School\Resources\StaffMembers;

use App\Filament\School\Resources\StaffMembers\Pages\CreateStaffMember;
use App\Filament\School\Resources\StaffMembers\Pages\EditStaffMember;
use App\Filament\School\Resources\StaffMembers\Pages\ListStaffMembers;
use App\Filament\School\Resources\StaffMembers\Schemas\StaffMemberForm;
use App\Filament\School\Resources\StaffMembers\Tables\StaffMembersTable;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Modules\Academic\Models\StaffMember;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StaffMemberResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = StaffMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::STAFF;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'personnel';

    protected static ?string $modelLabel = 'membre du personnel';

    protected static ?string $pluralModelLabel = 'personnel';

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function form(Schema $schema): Schema
    {
        return StaffMemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffMembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffMembers::route('/'),
            'create' => CreateStaffMember::route('/nouveau'),
            'edit' => EditStaffMember::route('/{record}/modifier'),
        ];
    }
}
