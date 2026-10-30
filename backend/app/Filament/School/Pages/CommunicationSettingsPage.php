<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Support\NavigationGroups;
use App\Modules\Communication\Models\CommunicationSetting;
use App\Modules\Core\Actions\RecordAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Réglages de communication : nom d'expéditeur des SMS et SMS
 * automatiques. Les notifications dans l'app sont toujours envoyées
 * (gratuites) ; les SMS ont un coût et se choisissent un par un.
 *
 * @property-read Schema $form
 */
class CommunicationSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 70;

    protected static ?string $slug = 'reglages-communication';

    protected static ?string $title = 'Réglages de communication';

    protected static ?string $navigationLabel = 'Communication';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('communication.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(CommunicationSetting::current()->only(['sms_sender', 'sms_on_absence', 'sms_on_payment', 'sms_on_report_card']));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        $isDevelopmentDriver = config('talenda.sms.driver') === 'log';

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Envoi des SMS')
                    ->components([
                        Text::make('Pilote de développement : les SMS sont préparés et visibles dans le journal SMS, mais aucun n\'est réellement envoyé. Le fournisseur réel (MTN, Airtel) sera branché à la mise en production.')
                            ->color('warning')
                            ->visible($isDevelopmentDriver),
                        TextInput::make('sms_sender')
                            ->label('Nom d\'expéditeur')
                            ->placeholder((string) config('talenda.sms.default_sender'))
                            ->helperText('Affiché à la place du numéro sur le téléphone des parents. 11 caractères maximum, lettres et chiffres.')
                            ->maxLength(11)
                            ->regex('/^[A-Za-z0-9 ]*$/'),
                    ]),
                Section::make('SMS automatiques')
                    ->description('Les parents qui ont l\'app reçoivent toujours ces informations dans l\'app (gratuit). Activez le SMS pour toucher aussi les familles sans smartphone ; chaque SMS est facturé.')
                    ->components([
                        Toggle::make('sms_on_absence')->label('Absence relevée à l\'appel')->helperText('Un SMS au contact principal dès que l\'élève est noté absent.'),
                        Toggle::make('sms_on_payment')->label('Paiement enregistré à la caisse')->helperText('Montant, référence du reçu et reste à payer.'),
                        Toggle::make('sms_on_report_card')->label('Bulletin publié')->helperText('Moyenne et rang du trimestre.'),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer')
                ->icon(Heroicon::OutlinedCheck)
                ->action(function (): void {
                    $state = $this->form->getState();
                    $state['sms_sender'] = blank($state['sms_sender'] ?? null) ? null : mb_strtoupper(trim((string) $state['sms_sender']));

                    CommunicationSetting::current()->update($state);
                    app(RecordAudit::class)->handle('communication.settings_updated', actor: Filament::auth()->user(), metadata: $state);

                    Notification::make()->success()->title('Réglages de communication enregistrés.')->send();
                }),
        ];
    }
}
