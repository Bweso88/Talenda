<?php

namespace App\Filament\School\Resources\SchoolYears;

use App\Modules\Academic\Actions\ChangeSchoolYearStatus;
use App\Modules\Academic\Actions\PrepareNextSchoolYear;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Actions du cycle de vie d'une année scolaire, partagées par la liste et
 * la page d'édition.
 */
final class SchoolYearActions
{
    public static function open(): Action
    {
        return Action::make('open')
            ->label('Ouvrir l\'année')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('L\'année ouverte est celle utilisée par défaut pour les inscriptions, les notes et la caisse.')
            ->visible(fn (SchoolYear $record): bool => $record->status === SchoolYearStatus::Draft
                && (Filament::auth()->user()?->can('update', $record) ?? false))
            ->action(self::guarded(fn (SchoolYear $record) => app(ChangeSchoolYearStatus::class)->open($record, Filament::auth()->user()),
                'Année ouverte.'));
    }

    public static function close(): Action
    {
        return Action::make('close')
            ->label('Clôturer l\'année')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Une année clôturée ne peut plus être modifiée ni rouverte : classes et notes sont figées. Les arriérés de paiement restent encaissables.')
            ->visible(fn (SchoolYear $record): bool => $record->isOpen()
                && (Filament::auth()->user()?->can('update', $record) ?? false))
            ->action(self::guarded(fn (SchoolYear $record) => app(ChangeSchoolYearStatus::class)->close($record, Filament::auth()->user()),
                'Année clôturée.'));
    }

    public static function prepareNext(): Action
    {
        return Action::make('prepareNext')
            ->label('Préparer l\'année suivante')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->requiresConfirmation()
            ->modalDescription('Crée l\'année suivante « en préparation » avec les mêmes classes, matières, coefficients et enseignants. Vous pourrez tout ajuster avant de l\'ouvrir.')
            ->visible(fn (SchoolYear $record): bool => ! SchoolYear::query()->where('starts_on', '>', $record->starts_on)->exists()
                && (Filament::auth()->user()?->can('create', SchoolYear::class) ?? false))
            ->action(self::guarded(fn (SchoolYear $record) => app(PrepareNextSchoolYear::class)->handle($record),
                'Année suivante préparée.'));
    }

    /**
     * Affiche une règle métier non respectée comme une notification au lieu
     * d'une erreur.
     */
    private static function guarded(Closure $callback, string $successMessage): Closure
    {
        return function (SchoolYear $record) use ($callback, $successMessage): void {
            try {
                $callback($record);
            } catch (BusinessRuleViolation $exception) {
                Notification::make()->danger()->title($exception->getMessage())->send();

                return;
            }

            Notification::make()->success()->title($successMessage)->send();
        };
    }
}
