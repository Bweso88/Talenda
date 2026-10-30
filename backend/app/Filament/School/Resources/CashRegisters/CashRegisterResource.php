<?php

namespace App\Filament\School\Resources\CashRegisters;

use App\Filament\School\Resources\CashRegisters\Pages\ManageCashRegisters;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\School\Support\TenantUnique;
use App\Filament\Support\FrenchLabels;
use App\Modules\Academic\Models\Site;
use App\Modules\Finances\Enums\CashRegisterKind;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CashRegisterResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = CashRegister::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCE_SETTINGS;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'caisses';

    protected static ?string $modelLabel = 'caisse';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->placeholder('Caisse du secondaire, Caisse centrale, BGFI…')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                Select::make('kind')
                    ->label('Nature')
                    ->options(CashRegisterKind::class)
                    ->default(CashRegisterKind::Cash->value)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, $state) => $set('account_code', ($state instanceof CashRegisterKind ? $state : CashRegisterKind::from($state))->defaultAccount()))
                    ->disabled(fn (?CashRegister $record): bool => $record?->isInUse() ?? false)
                    ->required(),
                TextInput::make('account_code')
                    ->label('Compte SYSCOHADA')
                    ->helperText('571x caisse, 552x mobile money, 521x banque. Un sous-compte par caisse si votre comptable le souhaite (57111, 57112…).')
                    ->default(CashRegisterKind::Cash->defaultAccount())
                    ->regex('/^5\d{1,7}$/')
                    ->required(),
                Select::make('site_id')
                    ->label('Site')
                    ->relationship('site', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                    ->default(fn (): ?int => Site::main()?->id)
                    ->visible(fn (): bool => Site::isMultiSite()),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['openSession.opener', 'site']))
            ->columns([
                TextColumn::make('name')->label('Caisse')->weight('bold'),
                TextColumn::make('kind')->label('Nature'),
                TextColumn::make('site.name')->label('Site')->visible(fn (): bool => Site::isMultiSite()),
                TextColumn::make('account_code')->label('Compte'),
                TextColumn::make('balance')->label('Solde')->alignEnd()
                    ->state(fn (CashRegister $record): string => Money::format($record->balance())),
                TextColumn::make('openSession.opener.name')->label('Ouverte par')->placeholder('Fermée'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCashRegisters::route('/'),
        ];
    }
}
