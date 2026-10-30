<?php

namespace App\Filament\School\Resources\StaffMembers\Schemas;

use App\Filament\School\Support\TenantUnique;
use App\Modules\Academic\Enums\StaffPosition;
use App\Modules\Core\Support\PhoneNumber;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(3)
                    ->components([
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('first_name')
                            ->label('Prénom(s)')
                            ->required()
                            ->maxLength(100),
                        Select::make('gender')
                            ->label('Sexe')
                            ->options(['female' => 'Femme', 'male' => 'Homme']),
                    ]),
                Section::make('Fonction et contact')
                    ->columns(3)
                    ->components([
                        Select::make('position')
                            ->label('Fonction')
                            ->options(StaffPosition::class)
                            ->default(StaffPosition::Teacher)
                            ->required(),
                        TextInput::make('employee_number')
                            ->label('Matricule')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                        Toggle::make('is_active')
                            ->label('En poste')
                            ->default(true)
                            ->inline(false),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->placeholder('06 123 45 67')
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
                                    $fail('Numéro de téléphone invalide.');
                                }
                            }),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),
                    ]),
            ]);
    }
}
