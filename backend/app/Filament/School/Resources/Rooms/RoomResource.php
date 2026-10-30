<?php

namespace App\Filament\School\Resources\Rooms;

use App\Filament\School\Resources\Rooms\Pages\ManageRooms;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Modules\Academic\Models\Room;
use App\Modules\Academic\Models\Site;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Salles de chaque site, préenregistrées pour être choisies sur les classes.
 */
class RoomResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'salles';

    protected static ?string $modelLabel = 'salle';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->label('Site')
                    ->relationship('site', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                    ->default(fn (): ?int => Site::main()?->id)
                    ->live()
                    ->required(),
                TextInput::make('name')
                    ->label('Nom')
                    ->placeholder('Salle 12, Labo de SVT…')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                        ->where('tenant_id', filament()->getTenant()?->getKey())
                        ->where('site_id', $get('site_id'))),
                TextInput::make('capacity')
                    ->label('Places')
                    ->numeric()->integer()->minValue(1),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('site'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Salle')->weight('bold')->searchable(),
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('capacity')->label('Places')->placeholder('—'),
                TextColumn::make('school_classes_count')->label('Classes')->counts('schoolClasses'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('site_id')->label('Site')->relationship('site', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRooms::route('/'),
        ];
    }
}
