<?php

namespace App\Filament\School\Resources\Streams;

use App\Filament\School\Resources\Streams\Pages\ManageStreams;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\School\Support\TenantUnique;
use App\Modules\Academic\Models\Stream;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StreamResource extends Resource
{
    protected static ?string $model = Stream::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'series';

    protected static ?string $modelLabel = 'série';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->helperText('Exemple : A, C, D.')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Code'),
                TextColumn::make('name')->label('Série'),
                TextColumn::make('school_classes_count')->label('Classes')->counts('schoolClasses'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStreams::route('/'),
        ];
    }
}
