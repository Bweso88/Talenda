<?php

namespace App\Filament\School\Resources\StaffMembers;

use App\Modules\Academic\Actions\GrantStaffAccess;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class StaffMemberActions
{
    /**
     * Ouvre un accès au back-office. Le mot de passe provisoire n'est
     * affiché qu'une fois, à l'écran de la personne qui ouvre l'accès.
     */
    public static function grantAccess(): Action
    {
        return Action::make('grantAccess')
            ->label('Ouvrir un accès')
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn (StaffMember $record): bool => Filament::auth()->user()?->can('grantAccess', $record) ?? false)
            ->modalDescription('La personne se connectera avec son téléphone ou son e-mail. Si elle a déjà un compte Talenda (parent, autre école), elle garde son mot de passe.')
            ->schema([
                Select::make('role')
                    ->label('Rôle')
                    ->options(self::roleOptions())
                    ->default(fn (StaffMember $record): ?string => $record->position->defaultRole())
                    ->required(),
            ])
            ->action(function (StaffMember $record, array $data): void {
                try {
                    $temporaryPassword = app(GrantStaffAccess::class)->handle($record, $data['role'], Filament::auth()->user());
                } catch (BusinessRuleViolation $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("Accès ouvert pour {$record->full_name}")
                    ->body($temporaryPassword === null
                        ? 'Cette personne avait déjà un compte : elle se connecte avec son mot de passe habituel.'
                        : "Mot de passe provisoire : **{$temporaryPassword}** — à communiquer à la personne, qui devra le changer à sa première connexion. Il ne sera plus affiché.")
                    ->persistent()
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        return [
            'school_admin' => 'Administrateur de l\'école',
            'director' => 'Direction',
            'academic_director' => 'Directeur(trice) des études / censeur',
            'secretary' => 'Secrétariat',
            'accountant' => 'Comptabilité',
            'cashier' => 'Caisse',
            'teacher' => 'Enseignant(e)',
            'supervisor' => 'Surveillance',
        ];
    }
}
