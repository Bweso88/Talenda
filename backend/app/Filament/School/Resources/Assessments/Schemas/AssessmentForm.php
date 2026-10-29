<?php

namespace App\Filament\School\Resources\Assessments\Schemas;

use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Models\AssessmentType;
use App\Modules\Assessments\Support\GradableClassSubjects;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->components([
                        Select::make('class_subject_id')
                            ->label('Classe et matière')
                            ->options(function (): array {
                                /** @var User $user */
                                $user = Filament::auth()->user();

                                return GradableClassSubjects::options($user);
                            })
                            ->searchable()
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('term_id')
                            ->label('Période')
                            ->options(fn (): array => Term::query()
                                ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                                ->where('status', TermStatus::Open)
                                ->orderBy('position')
                                ->pluck('name', 'id')
                                ->all())
                            ->default(fn (): ?int => Term::query()
                                ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                                ->where('status', TermStatus::Open)
                                ->whereDate('starts_on', '<=', today())
                                ->orderByDesc('position')
                                ->value('id'))
                            ->helperText('Seules les périodes ouvertes à la saisie sont proposées.')
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('assessment_type_id')
                            ->label('Type')
                            ->options(fn (): array => AssessmentType::query()->orderBy('position')->pluck('name', 'id')->all())
                            ->required(),
                        TextInput::make('title')
                            ->label('Intitulé')
                            ->placeholder('Devoir n° 1 — Fractions')
                            ->required()
                            ->maxLength(150),
                        DatePicker::make('held_on')
                            ->label('Date')
                            ->default(today())
                            ->required(),
                        TextInput::make('max_score')
                            ->label('Noté sur')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(20)
                            ->helperText('Les notes sont ramenées sur 20 pour les moyennes.')
                            ->required(),
                    ]),
            ]);
    }
}
