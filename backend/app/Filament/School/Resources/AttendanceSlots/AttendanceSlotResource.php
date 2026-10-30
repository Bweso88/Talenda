<?php

namespace App\Filament\School\Resources\AttendanceSlots;

use App\Filament\School\Resources\AttendanceSlots\Pages\ManageAttendanceSlots;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\School\Support\TenantUnique;
use App\Filament\Support\FrenchLabels;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use App\Modules\SchoolLife\Support\AttendanceTotal;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Créneaux d'appel. Les heures d'absence sont comptées sur leur durée.
 */
class AttendanceSlotResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = AttendanceSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'creneaux-d-appel';

    protected static ?string $modelLabel = 'créneau d\'appel';

    protected static ?string $pluralModelLabel = 'créneaux d\'appel';

    protected static ?string $navigationLabel = 'Créneaux d\'appel';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('name')->label('Nom')->required()->maxLength(50)->placeholder('Matin')
                    ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                TimePicker::make('starts_at')->label('Début')->seconds(false)->required(),
                TimePicker::make('ends_at')->label('Fin')->seconds(false)->required()->after('starts_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label('Créneau')->weight('bold'),
                TextColumn::make('starts_at')->label('Début')->formatStateUsing(fn (string $state): string => substr($state, 0, 5)),
                TextColumn::make('ends_at')->label('Fin')->formatStateUsing(fn (string $state): string => substr($state, 0, 5)),
                TextColumn::make('duration')->label('Durée comptée')
                    ->state(fn (AttendanceSlot $record): string => AttendanceTotal::hours($record->durationMinutes())),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendanceSlots::route('/'),
        ];
    }
}
