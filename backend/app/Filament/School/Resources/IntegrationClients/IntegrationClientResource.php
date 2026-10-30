<?php

namespace App\Filament\School\Resources\IntegrationClients;

use App\Filament\School\Resources\IntegrationClients\Pages\ManageIntegrationClients;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Models\User;
use App\Modules\Integrations\Actions\ManageIntegrationClients as ManageClients;
use App\Modules\Integrations\Models\IntegrationClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Applications connectées à l'école (Scolia) : création et révocation des
 * clés d'accès à l'API d'intégration.
 */
class IntegrationClientResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = IntegrationClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 80;

    protected static ?string $slug = 'applications-connectees';

    protected static ?string $navigationLabel = 'Applications connectées';

    protected static ?string $modelLabel = 'application connectée';

    protected static ?string $pluralModelLabel = 'applications connectées';

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->can('integration.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Application')->weight('bold'),
                TextColumn::make('token_prefix')->label('Clé')->formatStateUsing(fn (string $state): string => "{$state}…"),
                TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i')
                    ->description(fn (IntegrationClient $record): ?string => $record->creator?->name),
                TextColumn::make('last_used_at')->label('Dernière utilisation')->since()->placeholder('Jamais'),
                TextColumn::make('status')->label('Statut')->badge()
                    ->state(fn (IntegrationClient $record): string => $record->revoked_at === null ? 'Active' : 'Révoquée')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (IntegrationClient $record): bool => $record->revoked_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('L\'application ne pourra plus lire les données de l\'école ni connecter ses utilisateurs, jusqu\'à ce qu\'une nouvelle clé lui soit donnée.')
                    ->action(function (IntegrationClient $record): void {
                        /** @var User $user */
                        $user = Filament::auth()->user();
                        app(ManageClients::class)->revoke($record, $user);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIntegrationClients::route('/'),
        ];
    }
}
