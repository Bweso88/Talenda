<?php

namespace App\Filament\School\Widgets;

use App\Filament\School\Pages\ClassCouncil;
use App\Filament\School\Pages\TakeAttendancePage;
use App\Filament\School\Pages\UnpaidReport;
use App\Filament\School\Resources\AbsenceJustifications\AbsenceJustificationResource;
use App\Filament\School\Resources\CashOperations\CashOperationResource;
use App\Filament\School\Resources\Enrollments\EnrollmentResource;
use App\Filament\School\Resources\SmsMessages\SmsMessageResource;
use App\Filament\School\Support\DashboardFigures;
use App\Models\User;
use App\Modules\Finances\Support\Money;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Ce qui attend une décision ou une action aujourd'hui, selon le rôle.
 */
class PriorityActions extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.school.widgets.priority-actions';

    /**
     * @var int|string|array<string, int>
     */
    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        return self::items() !== [] || (Filament::auth()->user()?->can('student.view') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['items' => self::items()];
    }

    /**
     * @return list<array{title: string, text: string, badge: string, tone: string, icon: Heroicon, url: string}>
     */
    private static function items(): array
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        if ($user === null) {
            return [];
        }

        $figures = app(DashboardFigures::class);
        $items = [];

        if ($user->can('attendance.manage') && ($count = $figures->pendingJustifications()) > 0) {
            $items[] = ['title' => 'Justificatifs à examiner', 'text' => 'Déposés par les parents depuis l\'app.', 'badge' => (string) $count, 'tone' => 'warning', 'icon' => Heroicon::OutlinedDocumentCheck, 'url' => AbsenceJustificationResource::getUrl()];
        }

        if ($user->can('attendance.manage') && ($count = $figures->missingCalls()) > 0) {
            $items[] = ['title' => 'Appels pas encore faits', 'text' => 'Classes et créneaux de la journée sans appel.', 'badge' => (string) $count, 'tone' => 'warning', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => TakeAttendancePage::getUrl()];
        }

        if ($user->can('finance.view') && ($overdue = $figures->overdue())['amount'] > 0) {
            $items[] = ['title' => 'Retards de paiement', 'text' => Money::format($overdue['amount']).' en retard : relance par SMS possible.', 'badge' => "{$overdue['families']} famille(s)", 'tone' => 'danger', 'icon' => Heroicon::OutlinedExclamationTriangle, 'url' => UnpaidReport::getUrl()];
        }

        if ($user->can('treasury.approve') && ($count = $figures->treasury()['pending']) > 0) {
            $items[] = ['title' => 'Sorties d\'argent à valider', 'text' => 'Dépenses, salaires ou avances au-delà du seuil.', 'badge' => (string) $count, 'tone' => 'warning', 'icon' => Heroicon::OutlinedBanknotes, 'url' => CashOperationResource::getUrl()];
        }

        if ($user->can('treasury.approve') && ($advances = $figures->treasury())['advances'] > 0) {
            $items[] = ['title' => 'Avances à justifier', 'text' => Money::format($advances['advances_amount']).' remis, facture et monnaie pas encore rapportées.', 'badge' => (string) $advances['advances'], 'tone' => 'danger', 'icon' => Heroicon::OutlinedHandRaised, 'url' => CashOperationResource::getUrl()];
        }

        if ($user->can('enrollment.manage') && ($count = $figures->preRegistered()) > 0) {
            $items[] = ['title' => 'Préinscriptions à confirmer', 'text' => 'Dossiers en attente de confirmation.', 'badge' => (string) $count, 'tone' => 'primary', 'icon' => Heroicon::OutlinedUserPlus, 'url' => EnrollmentResource::getUrl()];
        }

        if ($user->can('term.manage') && ($term = $figures->lockedTerm()) !== null) {
            $items[] = ['title' => "Bulletins du {$term->name} à publier", 'text' => 'La saisie est verrouillée : conseil de classe puis publication.', 'badge' => 'Conseil', 'tone' => 'primary', 'icon' => Heroicon::OutlinedPresentationChartLine, 'url' => ClassCouncil::getUrl()];
        }

        if ($user->can('communication.manage') && ($count = $figures->failedSms()) > 0) {
            $items[] = ['title' => 'SMS non remis', 'text' => 'Échecs d\'envoi des 7 derniers jours.', 'badge' => (string) $count, 'tone' => 'danger', 'icon' => Heroicon::OutlinedDevicePhoneMobile, 'url' => SmsMessageResource::getUrl()];
        }

        return $items;
    }
}
