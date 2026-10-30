<?php

namespace App\Filament\School\Resources\SchoolClasses\Schemas;

use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Site;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                            ->visible(fn (): bool => Site::isMultiSite())
                            ->required(fn (): bool => Site::isMultiSite()),
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
                        TextInput::make('room')
                            ->label('Salle')
                            ->maxLength(50),
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
