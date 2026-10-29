<?php

namespace App\Filament\School\Resources\Invoices;

use App\Filament\School\Pages\CashDesk;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Finances\Actions\ApplyFeeGrid;
use App\Modules\Finances\Actions\ManageInvoice;
use App\Modules\Finances\Models\FeeType;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\PaymentPlan;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Actions de la fiche facture. Toutes passent par ManageInvoice : ajout
 * de lignes, jamais de modification.
 */
final class InvoiceActions
{
    public static function collect(): Action
    {
        return Action::make('collect')
            ->label('Encaisser')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (): bool => self::userCan('payment.record'))
            ->url(fn (Invoice $record): string => CashDesk::getUrl(['facture' => $record->uuid]));
    }

    public static function addFee(): Action
    {
        return self::manage('addFee', 'Ajouter un frais', Heroicon::OutlinedPlusCircle)
            ->schema([
                Select::make('fee_type_id')
                    ->label('Type de frais')
                    ->options(fn (): array => FeeType::query()->orderBy('position')->pluck('name', 'id')->all())
                    ->required(),
                TextInput::make('amount')
                    ->label('Montant')
                    ->helperText('Laisser vide pour le montant de la grille tarifaire.')
                    ->numeric()->integer()->minValue(1)->suffix('FCFA'),
            ])
            ->action(self::guarded(fn (Invoice $record, array $data) => app(ManageInvoice::class)->addFee(
                $record,
                FeeType::query()->findOrFail($data['fee_type_id']),
                filled($data['amount'] ?? null) ? (int) $data['amount'] : null,
                Filament::auth()->user(),
            ), 'Frais ajouté.'));
    }

    public static function addDiscount(): Action
    {
        return self::manage('addDiscount', 'Accorder une réduction', Heroicon::OutlinedReceiptPercent)
            ->schema([
                Select::make('label')
                    ->label('Réduction')
                    ->options([
                        'Réduction fratrie' => 'Fratrie',
                        'Réduction enfant du personnel' => 'Enfant du personnel',
                        'Bourse' => 'Bourse',
                        'Réduction exceptionnelle' => 'Autre',
                    ])
                    ->required(),
                TextInput::make('amount')
                    ->label('Montant de la réduction')
                    ->numeric()->integer()->minValue(1)->suffix('FCFA')
                    ->required(),
                TextInput::make('reason')
                    ->label('Motif')
                    ->placeholder('Ex. 3e enfant inscrit, décision du directeur du 12/10')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(self::guarded(fn (Invoice $record, array $data) => app(ManageInvoice::class)->addDiscount(
                $record, (int) $data['amount'], $data['label'], $data['reason'], Filament::auth()->user(),
            ), 'Réduction accordée.'));
    }

    public static function addAdjustment(): Action
    {
        return self::manage('addAdjustment', 'Corriger', Heroicon::OutlinedPencilSquare)
            ->schema([
                TextInput::make('amount')
                    ->label('Montant (négatif pour diminuer)')
                    ->numeric()->integer()->suffix('FCFA')
                    ->required(),
                TextInput::make('label')
                    ->label('Libellé')
                    ->required()
                    ->maxLength(150),
                TextInput::make('reason')
                    ->label('Motif')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(self::guarded(fn (Invoice $record, array $data) => app(ManageInvoice::class)->addAdjustment(
                $record, (int) $data['amount'], $data['label'], $data['reason'], Filament::auth()->user(),
            ), 'Correction enregistrée.'));
    }

    public static function cancelRemaining(): Action
    {
        return self::manage('cancelRemaining', 'Remettre le reste dû', Heroicon::OutlinedHandRaised)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Le reste à payer est annulé (départ, geste de la direction). Les sommes déjà payées restent acquises.')
            ->schema([
                TextInput::make('reason')->label('Motif')->required()->maxLength(255),
            ])
            ->action(self::guarded(fn (Invoice $record, array $data) => app(ManageInvoice::class)->cancelRemainingBalance(
                $record, $data['reason'], Filament::auth()->user(),
            ), 'Reste dû remis.'));
    }

    public static function changePlan(): Action
    {
        return self::manage('changePlan', 'Changer de plan de paiement', Heroicon::OutlinedCalendar)
            ->fillForm(fn (Invoice $record): array => ['payment_plan_id' => $record->payment_plan_id])
            ->schema([
                Select::make('payment_plan_id')
                    ->label('Plan de paiement')
                    ->helperText('Les paiements déjà faits restent acquis ; l\'échéancier est recalculé.')
                    ->options(fn (Invoice $record): array => PaymentPlan::query()
                        ->where('school_year_id', $record->school_year_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required(),
            ])
            ->action(self::guarded(fn (Invoice $record, array $data) => app(ManageInvoice::class)->changePaymentPlan(
                $record, PaymentPlan::query()->findOrFail($data['payment_plan_id']), Filament::auth()->user(),
            ), 'Plan de paiement modifié.'));
    }

    public static function applyGrid(): Action
    {
        return self::manage('applyGrid', 'Appliquer la grille', Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalDescription('Ajoute les frais obligatoires de la grille tarifaire qui manquent à cette facture.')
            ->action(self::guarded(fn (Invoice $record) => app(ApplyFeeGrid::class)->handle($record, Filament::auth()->user()), 'Facture complétée.'));
    }

    private static function manage(string $name, string $label, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (Invoice $record): bool => Filament::auth()->user()?->can('update', $record) ?? false);
    }

    private static function guarded(Closure $callback, string $successMessage): Closure
    {
        return function (Invoice $record, array $data = []) use ($callback, $successMessage): void {
            try {
                $callback($record, $data);
            } catch (BusinessRuleViolation $exception) {
                Notification::make()->danger()->title($exception->getMessage())->send();

                return;
            }

            Notification::make()->success()->title($successMessage)->send();
        };
    }

    private static function userCan(string $permission): bool
    {
        return Filament::auth()->user()?->can($permission) ?? false;
    }
}
