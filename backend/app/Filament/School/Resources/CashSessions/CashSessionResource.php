<?php

namespace App\Filament\School\Resources\CashSessions;

use App\Filament\School\Resources\CashSessions\Pages\ListCashSessions;
use App\Filament\School\Resources\CashSessions\Pages\ViewCashSession;
use App\Filament\School\Resources\CashSessions\Schemas\CashSessionInfolist;
use App\Filament\School\Resources\CashSessions\Tables\CashSessionsTable;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Modules\Finances\Models\CashSession;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Historique des sessions de caisse (contrôle de la comptabilité et de la
 * direction). Lecture seule : ouverture et clôture se font depuis la caisse.
 */
class CashSessionResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = CashSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCES;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'sessions-de-caisse';

    protected static ?string $navigationLabel = 'Sessions de caisse';

    protected static ?string $modelLabel = 'session de caisse';

    protected static ?string $pluralModelLabel = 'sessions de caisse';

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->can('finance.manage') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return CashSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashSessions::route('/'),
            'view' => ViewCashSession::route('/{record}'),
        ];
    }
}
