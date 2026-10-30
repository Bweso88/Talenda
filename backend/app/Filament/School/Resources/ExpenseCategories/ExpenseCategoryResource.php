<?php

namespace App\Filament\School\Resources\ExpenseCategories;

use App\Filament\School\Resources\ExpenseCategories\Pages\ManageExpenseCategories;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\School\Support\TenantUnique;
use App\Filament\Support\FrenchLabels;
use App\Modules\Finances\Models\ExpenseCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Catégories de dépense et leur compte de charge SYSCOHADA.
 */
class ExpenseCategoryResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCE_SETTINGS;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'categories-de-depense';

    protected static ?string $modelLabel = 'catégorie de dépense';

    protected static ?string $pluralModelLabel = 'catégories de dépense';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->placeholder('Eau')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                TextInput::make('account_code')
                    ->label('Compte SYSCOHADA')
                    ->helperText('Compte de charge (classe 6), ex. 6051 pour l\'eau. À faire valider par votre comptable.')
                    ->required()
                    ->regex('/^6\d{1,7}$/')
                    ->maxLength(10),
                TextInput::make('position')
                    ->label('Ordre d\'affichage')
                    ->numeric()->integer()->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->label('Catégorie')->weight('bold'),
                TextColumn::make('account_code')->label('Compte'),
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
            'index' => ManageExpenseCategories::route('/'),
        ];
    }
}
