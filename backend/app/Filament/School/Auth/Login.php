<?php

namespace App\Filament\School\Auth;

use App\Modules\Core\Auth\LoginIdentifier;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Connexion au back-office école par e-mail **ou** téléphone : beaucoup de
 * membres du personnel n'ont pas d'adresse e-mail.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('E-mail ou téléphone')
            ->placeholder('06 123 45 67')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $credentials = LoginIdentifier::toCredentials((string) $data['login']);

        if ($credentials === null) {
            $this->throwFailureValidationException();
        }

        return [...$credentials, 'password' => $data['password']];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
