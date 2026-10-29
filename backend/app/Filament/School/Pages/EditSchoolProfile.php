<?php

namespace App\Filament\School\Pages;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Support\PhoneNumber;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Identité de l'école : nom, coordonnées, logo et couleur (marque blanche,
 * en-tête des documents imprimés). Accessible depuis le menu de l'école.
 */
class EditSchoolProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Profil de l\'école';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'profil';
    }

    public static function canView(Model $tenant): bool
    {
        return Filament::auth()->user()?->can('tenant.settings.manage') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('École')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nom officiel')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('settings.short_name')
                            ->label('Nom court')
                            ->helperText('Affiché dans l\'application mobile.')
                            ->maxLength(50),
                        TextInput::make('settings.motto')
                            ->label('Devise')
                            ->maxLength(255),
                        TextInput::make('settings.director_name')
                            ->label('Nom du chef d\'établissement')
                            ->helperText('Imprimé sur les certificats et bulletins.')
                            ->maxLength(255),
                    ]),
                Section::make('Coordonnées')
                    ->columns(2)
                    ->components([
                        TextInput::make('settings.address')
                            ->label('Adresse')
                            ->maxLength(255),
                        TextInput::make('settings.city')
                            ->label('Ville')
                            ->maxLength(100),
                        TextInput::make('settings.phone')
                            ->label('Téléphone')
                            ->tel()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
                                    $fail('Numéro de téléphone invalide.');
                                }
                            }),
                        TextInput::make('settings.email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),
                    ]),
                Section::make('Apparence')
                    ->columns(2)
                    ->components([
                        FileUpload::make('settings.logo_path')
                            ->label('Logo')
                            ->image()
                            ->maxSize(1024)
                            ->disk('public')
                            ->directory(fn (): string => 'tenants/'.Filament::getTenant()?->getKey().'/branding')
                            ->visibility('public'),
                        ColorPicker::make('settings.primary_color')
                            ->label('Couleur principale')
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->tenant;

        $data['settings'] = $tenant->settings?->attributesToArray() ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Tenant $record */
        $settings = $data['settings'] ?? [];

        if (filled($settings['phone'] ?? null)) {
            $settings['phone'] = PhoneNumber::normalize($settings['phone']);
        }

        $record->update(['name' => $data['name']]);
        $record->settings()->updateOrCreate([], $settings);

        app(RecordAudit::class)->handle('tenant.profile_updated', actor: Filament::auth()->user(), subject: $record);

        return $record;
    }
}
