<?php

namespace App\Filament\Platform\Resources\Tenants\Schemas;

use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Support\CountryPresets;
use App\Modules\Core\Support\PhoneNumber;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('École')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nom de l\'école')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Identifiant dans l\'adresse')
                            ->helperText('Utilisé dans l\'adresse du back-office : /ecole/{identifiant}. Non modifiable ensuite.')
                            ->required()
                            ->maxLength(63)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),
                        Select::make('country_code')
                            ->label('Pays')
                            ->options(CountryPresets::options())
                            ->default(CountryPresets::defaultCode())
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('status')
                            ->label('Statut')
                            ->options(TenantStatus::class)
                            ->required()
                            ->visibleOn('edit'),
                    ]),

                Section::make('Premier administrateur de l\'école')
                    ->description('Un compte existant (même e-mail ou téléphone) est réutilisé. Sinon un compte est créé, avec changement de mot de passe obligatoire à la première connexion.')
                    ->columns(2)
                    ->visibleOn('create')
                    ->components([
                        TextInput::make('admin_name')
                            ->label('Nom complet')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('admin_email')
                            ->label('E-mail')
                            ->email()
                            ->requiredWithout('admin_phone'),
                        TextInput::make('admin_phone')
                            ->label('Téléphone')
                            ->tel()
                            ->placeholder('06 123 45 67')
                            ->requiredWithout('admin_email')
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (filled($value) && PhoneNumber::normalize((string) $value, $get('country_code')) === null) {
                                    $fail('Numéro de téléphone invalide.');
                                }
                            }),
                        TextInput::make('admin_password')
                            ->label('Mot de passe provisoire')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(10),
                    ]),
            ]);
    }
}
