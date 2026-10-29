<?php

namespace App\Filament\School\Resources\Levels;

use App\Filament\School\Resources\Levels\Pages\ManageLevels;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\School\Support\TenantUnique;
use App\Modules\Academic\Models\Level;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LevelResource extends Resource
{
    protected static ?string $model = Level::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'niveaux';

    protected static ?string $modelLabel = 'niveau';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('cycle_id')
                    ->label('Cycle')
                    ->relationship('cycle', 'name')
                    ->required(),
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                TextInput::make('position')
                    ->label('Ordre')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                Select::make('next_level_id')
                    ->label('Niveau suivant')
                    ->helperText('Proposé lors du passage en classe supérieure.')
                    ->relationship(
                        'nextLevel',
                        'name',
                        modifyQueryUsing: fn (Builder $query, ?Level $record) => $query
                            ->when($record, fn (Builder $query, Level $level) => $query->whereKeyNot($level->getKey()))
                            ->orderBy('position'),
                    ),
                TextInput::make('exam_name')
                    ->label('Examen de fin de niveau')
                    ->helperText('Ex. CEPE, BEPC, Baccalauréat. Vide si aucun.')
                    ->maxLength(50),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->defaultGroup(Group::make('cycle.name')->label('Cycle')->orderQueryUsing(
                fn (Builder $query) => $query->orderBy('position'),
            ))
            ->columns([
                TextColumn::make('name')->label('Niveau'),
                TextColumn::make('code')->label('Code'),
                TextColumn::make('nextLevel.name')->label('Niveau suivant')->placeholder('—'),
                TextColumn::make('exam_name')->label('Examen')->badge()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('cycle')->label('Cycle')->relationship('cycle', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLevels::route('/'),
        ];
    }
}
