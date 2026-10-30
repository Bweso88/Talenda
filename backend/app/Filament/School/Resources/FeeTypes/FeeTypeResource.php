<?php

namespace App\Filament\School\Resources\FeeTypes;

use App\Filament\School\Resources\FeeTypes\Pages\ManageFeeTypes;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\School\Support\TenantUnique;
use App\Filament\Support\FrenchLabels;
use App\Modules\Finances\Enums\FeeAppliesTo;
use App\Modules\Finances\Enums\FeeBilling;
use App\Modules\Finances\Models\FeeType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FeeTypeResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = FeeType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCE_SETTINGS;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'types-de-frais';

    protected static ?string $navigationLabel = 'Types de frais';

    protected static ?string $modelLabel = 'type de frais';

    protected static ?string $pluralModelLabel = 'types de frais';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                Select::make('billing')
                    ->label('Échéance')
                    ->options(FeeBilling::class)
                    ->required(),
                Select::make('applies_to')
                    ->label('Concerne')
                    ->options(FeeAppliesTo::class)
                    ->default(FeeAppliesTo::All)
                    ->required(),
                Toggle::make('is_optional')
                    ->label('Facultatif')
                    ->helperText('Ajouté à la demande sur la facture (tenue, transport…), pas automatiquement à l\'inscription.'),
                TextInput::make('position')
                    ->label('Ordre')
                    ->numeric()
                    ->integer()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label('Type de frais')->weight('bold'),
                TextColumn::make('code')->label('Code'),
                TextColumn::make('billing')->label('Échéance'),
                TextColumn::make('applies_to')->label('Concerne'),
                IconColumn::make('is_optional')->label('Facultatif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFeeTypes::route('/'),
        ];
    }
}
