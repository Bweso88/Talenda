<?php

namespace App\Filament\School\Widgets;

use App\Filament\School\Pages\CashDesk;
use App\Filament\School\Pages\TakeAttendancePage;
use App\Filament\School\Resources\Announcements\AnnouncementResource;
use App\Filament\School\Resources\Students\StudentResource;
use App\Filament\School\Support\DashboardFigures;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Bandeau d'accueil : école, année, période en cours et raccourcis selon
 * le rôle.
 */
class WelcomeBanner extends Widget
{
    protected static ?int $sort = -10;

    // Affiché tout de suite : le bandeau ne doit pas clignoter au chargement.
    protected static bool $isLazy = false;

    protected string $view = 'filament.school.widgets.welcome-banner';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $figures = app(DashboardFigures::class);
        $tenant = Filament::getTenant();
        $hour = (int) now($tenant?->getAttribute('timezone') ?? config('app.timezone'))->format('H');

        $actions = array_values(array_filter([
            $user->can('attendance.take') ? ['label' => 'Faire l\'appel', 'url' => TakeAttendancePage::getUrl(), 'icon' => Heroicon::OutlinedClipboardDocumentCheck] : null,
            $user->can('payment.record') ? ['label' => 'Encaisser un versement', 'url' => CashDesk::getUrl(), 'icon' => Heroicon::OutlinedBanknotes] : null,
            $user->can('enrollment.manage') ? ['label' => 'Nouvelle inscription', 'url' => StudentResource::getUrl('create'), 'icon' => Heroicon::OutlinedUserPlus] : null,
            $user->can('communication.send') ? ['label' => 'Annonce aux familles', 'url' => AnnouncementResource::getUrl(), 'icon' => Heroicon::OutlinedMegaphone] : null,
        ]));

        return [
            'greeting' => ($hour >= 18 ? 'Bonsoir' : 'Bonjour').', '.$user->name,
            'school' => $tenant?->getAttribute('name'),
            'period' => trim(($figures->currentTerm()->name ?? '').' • '.($figures->year()->name ?? 'aucune année ouverte'), ' •'),
            'actions' => array_map(fn (array $action, int $index): array => [...$action, 'primary' => $index === 0], $actions, array_keys($actions)),
        ];
    }
}
