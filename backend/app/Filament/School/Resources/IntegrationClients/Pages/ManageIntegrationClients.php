<?php

namespace App\Filament\School\Resources\IntegrationClients\Pages;

use App\Filament\School\Resources\IntegrationClients\IntegrationClientResource;
use App\Models\User;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Integrations\Actions\ManageIntegrationClients as ManageClients;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageIntegrationClients extends ManageRecords
{
    protected static string $resource = IntegrationClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connect')
                ->label('Connecter une application')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (): bool => IntegrationClientResource::canCreate())
                ->modalDescription('Une clé secrète est créée pour l\'application. Elle donne accès aux classes, élèves, responsables et enseignants de l\'année en cours, et permet aux parents et enseignants de s\'y connecter avec leur compte Talenda.')
                ->schema([
                    Select::make('name')->label('Application')->options(['Scolia' => 'Scolia (cahier de liaison)'])->default('Scolia')->required(),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = Filament::auth()->user();

                    try {
                        [, $token] = app(ManageClients::class)->create((string) $data['name'], $user);
                    } catch (BusinessRuleViolation $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title("{$data['name']} est connecté")
                        ->body('Adresse de l\'API : **'.url('/api/integration/v1').'** — Clé : **'.$token.'** — À saisir dans l\'administration de '.$data['name'].'. La clé ne sera plus affichée.')
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
