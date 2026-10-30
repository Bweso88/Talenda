<?php

namespace App\Filament\School\Resources\SchoolClasses\Schemas;

use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\Room;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Site;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SchoolClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->components([
                        Select::make('school_year_id')
                            ->label('Année scolaire')
                            ->relationship(
                                'schoolYear',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->where('status', '!=', SchoolYearStatus::Closed)
                                    ->orderByDesc('starts_on'),
                            )
                            ->default(fn (): ?int => SchoolYear::query()->open()->value('id'))
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('site_id')
                            ->label('Site')
                            ->relationship('site', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                            ->default(fn (): ?int => Site::main()?->id)
                            ->helperText(fn (): ?string => Site::isMultiSite() ? null : 'Un seul site pour l\'instant : ajoutez-en dans Paramètres → Sites.')
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('room_id', null))
                            ->required(),
                        Select::make('level_id')
                            ->label('Niveau')
                            ->relationship('level', 'name', modifyQueryUsing: fn (Builder $query) => $query->orderBy('position'))
                            ->required(),
                        Select::make('stream_id')
                            ->label('Série')
                            ->helperText('Lycée uniquement.')
                            ->relationship('stream', 'name'),
                        TextInput::make('name')
                            ->label('Nom de la classe')
                            ->placeholder('6e B')
                            ->required()
                            ->maxLength(50)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, callable $get) => $rule->where('school_year_id', $get('school_year_id')),
                            ),
                        TextInput::make('capacity')
                            ->label('Effectif maximum')
                            ->numeric()
                            ->integer()
                            ->minValue(1),
                        Select::make('room_id')
                            ->label('Salle')
                            ->relationship('room', 'name', modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                ->where('site_id', $get('site_id'))
                                ->where('is_active', true)
                                ->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                $capacity = $state === null ? null : Room::query()->find($state)?->capacity;

                                if ($capacity !== null && blank($get('capacity'))) {
                                    $set('capacity', $capacity);
                                }
                            })
                            ->helperText('Salles du site (Paramètres → Salles). Une salle manquante se crée avec le bouton +.')
                            ->createOptionForm([
                                TextInput::make('name')->label('Nom de la salle')->placeholder('Salle 12')->required()->maxLength(50),
                                TextInput::make('capacity')->label('Places')->numeric()->integer()->minValue(1),
                            ])
                            ->createOptionUsing(fn (array $data, Get $get): int => Room::create([
                                'site_id' => $get('site_id'),
                                'name' => trim($data['name']),
                                'capacity' => $data['capacity'] ?? null,
                            ])->id)
                            ->createOptionModalHeading('Nouvelle salle'),
                        Select::make('head_teacher_id')
                            ->label('Professeur principal')
                            ->relationship(
                                'headTeacher',
                                'last_name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->full_name)
                            ->searchable(['first_name', 'last_name'])
                            ->columnSpan(2),
                    ]),
            ]);
    }
}
