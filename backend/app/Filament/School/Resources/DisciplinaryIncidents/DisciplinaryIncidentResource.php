<?php

namespace App\Filament\School\Resources\DisciplinaryIncidents;

use App\Filament\School\Resources\DisciplinaryIncidents\Pages\ManageDisciplinaryIncidents;
use App\Filament\School\Support\EnrollmentSelect;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Models\User;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\SchoolLife\Actions\RecordIncident;
use App\Modules\SchoolLife\Enums\Sanction;
use App\Modules\SchoolLife\Models\DisciplinaryIncident;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Registre de discipline : incidents et sanctions décidées.
 */
class DisciplinaryIncidentResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = DisciplinaryIncident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SCHOOL_LIFE;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'discipline';

    protected static ?string $navigationLabel = 'Discipline';

    protected static ?string $modelLabel = 'incident';

    protected static ?string $pluralModelLabel = 'incidents de discipline';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                EnrollmentSelect::make()->required()->columnSpanFull(),
                DatePicker::make('occurred_on')->label('Date')->required()->default(today()),
                Select::make('sanction')->label('Sanction')->options(Sanction::class)->required()->live()->default(Sanction::Warning->value),
                Textarea::make('description')->label('Faits')->required()->maxLength(1000)->rows(3)->columnSpanFull(),
                TextInput::make('suspension_days')->label('Jours d\'exclusion')->numeric()->minValue(1)->maxValue(365)
                    ->visible(fn (Get $get): bool => self::sanction($get('sanction')) === Sanction::Suspension)
                    ->required(fn (Get $get): bool => self::sanction($get('sanction')) === Sanction::Suspension),
                Toggle::make('parents_informed')->label('Parents informés')->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['enrollment.student', 'enrollment.schoolClass', 'reporter']))
            ->defaultSort('occurred_on', 'desc')
            ->columns([
                TextColumn::make('occurred_on')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('enrollment.student.full_name')->label('Élève')->weight('bold'),
                TextColumn::make('enrollment.schoolClass.name')->label('Classe'),
                TextColumn::make('description')->label('Faits')->limit(50)->tooltip(fn (DisciplinaryIncident $record): string => $record->description),
                TextColumn::make('sanction')->label('Sanction')->badge()
                    ->formatStateUsing(fn (DisciplinaryIncident $record): string => $record->sanction->getLabel()
                        .($record->suspension_days ? " ({$record->suspension_days} j)" : '')),
                IconColumn::make('parents_informed')->label('Parents informés')->boolean(),
                TextColumn::make('reporter.name')->label('Saisi par')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('sanction')->label('Sanction')->options(Sanction::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (DisciplinaryIncident $record, array $data): DisciplinaryIncident => self::save($data, $record)),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data, ?DisciplinaryIncident $incident = null): DisciplinaryIncident
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        try {
            /** @var array{enrollment_id: int|string, occurred_on: mixed, description: string, sanction: Sanction|string, suspension_days?: mixed, parents_informed?: bool|null} $data */
            return app(RecordIncident::class)->handle($data, $user, $incident);
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt;
        }
    }

    private static function sanction(mixed $state): ?Sanction
    {
        return $state instanceof Sanction ? $state : Sanction::tryFrom((string) $state);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDisciplinaryIncidents::route('/'),
        ];
    }
}
