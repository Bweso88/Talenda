<?php

namespace App\Filament\School\Resources\Sites;

use App\Filament\School\Resources\Sites\Pages\ManageSites;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\School\Support\TenantUnique;
use App\Filament\Support\FrenchLabels;
use App\Modules\Academic\Models\Site;
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
 * Sites de l'école (docs/TRESORERIE.md) : ses classes, donc ses élèves, et
 * ses caisses y sont rattachées.
 */
class SiteResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = Site::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'sites';

    protected static ?string $modelLabel = 'site';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->placeholder('Site de Poto-Poto')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                TextInput::make('address')
                    ->label('Adresse')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Site')->weight('bold'),
                TextColumn::make('address')->label('Adresse')->placeholder('—'),
                TextColumn::make('school_classes_count')->label('Classes')->counts('schoolClasses'),
                TextColumn::make('cash_registers_count')->label('Caisses')->counts('cashRegisters'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSites::route('/'),
        ];
    }
}
