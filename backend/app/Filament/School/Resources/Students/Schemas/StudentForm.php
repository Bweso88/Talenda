<?php

namespace App\Filament\School\Resources\Students\Schemas;

use App\Filament\School\Resources\Enrollments\EnrollmentActions;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\Gender;
use App\Modules\Enrollments\Enums\GuardianRelationship;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fiche élève. En création, elle comprend aussi les responsables et
 * l'inscription : une nouvelle inscription se fait en un seul écran.
 */
class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Élève')
                    ->columns(3)
                    ->components([
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('first_names')
                            ->label('Prénoms')
                            ->required()
                            ->maxLength(150),
                        Radio::make('gender')
                            ->label('Sexe')
                            ->options(Gender::class)
                            ->inline()
                            ->required(),
                        DatePicker::make('birth_date')
                            ->label('Date de naissance')
                            ->maxDate(now())
                            ->required(),
                        TextInput::make('birth_place')
                            ->label('Lieu de naissance')
                            ->maxLength(100),
                        Select::make('nationality')
                            ->label('Nationalité')
                            ->options(['CG' => 'Congolaise', 'CD' => 'Congolaise (RDC)', 'GA' => 'Gabonaise', 'CM' => 'Camerounaise', 'CF' => 'Centrafricaine', 'AO' => 'Angolaise', 'XX' => 'Autre'])
                            ->default('CG'),
                        TextInput::make('address')
                            ->label('Adresse / quartier')
                            ->maxLength(255),
                        TextInput::make('previous_school')
                            ->label('Ancien établissement')
                            ->maxLength(255),
                        TextInput::make('registration_number')
                            ->label('Matricule')
                            ->disabled()
                            ->visibleOn('edit'),
                        Textarea::make('medical_notes')
                            ->label('Informations utiles en cas d\'urgence')
                            ->helperText('Allergie, traitement… Rien de plus : pas de dossier médical.')
                            ->maxLength(500)
                            ->rows(2)
                            ->columnSpan(2),
                        FileUpload::make('photo_path')
                            ->label('Photo')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->disk('local')
                            ->visibility('private')
                            ->directory(fn (): string => 'tenants/'.Filament::getTenant()?->getKey().'/students'),
                    ]),

                Section::make('Responsables')
                    ->visibleOn('create')
                    ->components([
                        Repeater::make('guardians')
                            ->hiddenLabel()
                            ->addActionLabel('Ajouter un responsable')
                            ->defaultItems(1)
                            ->columns(3)
                            ->schema([
                                Select::make('relationship')
                                    ->label('Lien')
                                    ->options(GuardianRelationship::class)
                                    ->required(),
                                TextInput::make('last_name')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('first_names')
                                    ->label('Prénoms')
                                    ->maxLength(150),
                                TextInput::make('phone')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->placeholder('06 123 45 67')
                                    ->helperText('Un parent déjà connu (frère, sœur inscrits) est retrouvé par son numéro.')
                                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                        if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
                                            $fail('Numéro de téléphone invalide.');
                                        }
                                    }),
                                TextInput::make('profession')
                                    ->label('Profession')
                                    ->maxLength(100),
                                TextInput::make('email')
                                    ->label('E-mail')
                                    ->email(),
                                Toggle::make('is_primary_contact')
                                    ->label('Contact principal'),
                                Toggle::make('is_financially_responsible')
                                    ->label('Paie la scolarité'),
                            ]),
                    ]),

                Section::make('Inscription')
                    ->visibleOn('create')
                    ->columns(3)
                    ->components([
                        Select::make('school_class_id')
                            ->label('Classe')
                            ->options(fn (): array => SchoolClass::query()
                                ->where('school_year_id', EnrollmentActions::openYearId())
                                ->withCount(['enrollments as occupied_seats' => fn ($query) => $query->active()])
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (SchoolClass $schoolClass): array => [
                                    $schoolClass->id => $schoolClass->name.($schoolClass->capacity === null ? '' : " ({$schoolClass->getAttribute('occupied_seats')}/{$schoolClass->capacity})"),
                                ])
                                ->all())
                            ->helperText('Classes de l\'année ouverte (places occupées / capacité).')
                            ->required(),
                        Select::make('status')
                            ->label('Statut')
                            ->options([
                                EnrollmentStatus::Enrolled->value => 'Inscrit (dossier validé)',
                                EnrollmentStatus::PreRegistered->value => 'Préinscrit (place réservée)',
                            ])
                            ->default(EnrollmentStatus::Enrolled->value)
                            ->required(),
                        Toggle::make('allow_possible_duplicate')
                            ->label('Homonyme : inscrire quand même')
                            ->helperText('À cocher seulement si Talenda signale un élève existant qui est en réalité une autre personne.'),
                        CheckboxList::make('documents')
                            ->label('Pièces fournies')
                            ->options(fn (): array => EnrollmentActions::documentOptions())
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
