<?php

namespace App\Filament\School\Resources\Invoices\RelationManagers;

use App\Filament\School\Support\FinanceLinks;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Finances\Actions\ReversePayment;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\Money;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Paiements';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['recorder', 'reverser']))
            ->paginated(false)
            ->columns([
                TextColumn::make('receipt_number')->label('Reçu')->weight('bold'),
                TextColumn::make('paid_on')->label('Date')->date('d/m/Y'),
                TextColumn::make('amount')->label('Montant')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('method')->label('Moyen'),
                TextColumn::make('reference')->label('Référence')->placeholder('—'),
                TextColumn::make('recorder.name')->label('Encaissé par'),
                TextColumn::make('status')->label('Statut')->badge()
                    ->description(fn (Payment $record): ?string => $record->isValid() ? null : "{$record->reverser?->name} : {$record->reversal_reason}"),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label('Reçu')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Payment $record): string => FinanceLinks::receiptUrl($record), shouldOpenInNewTab: true),
                Action::make('reverse')
                    ->label('Annuler')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => Filament::auth()->user()?->can('reverse', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalDescription('Le paiement reste dans l\'historique, marqué annulé. Le reste à payer de la famille augmente d\'autant.')
                    ->schema([
                        TextInput::make('reason')->label('Motif de l\'annulation')->required()->maxLength(255),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            app(ReversePayment::class)->handle($record, $data['reason'], Filament::auth()->user());
                        } catch (BusinessRuleViolation $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title("Reçu {$record->receipt_number} annulé.")->send();
                    }),
            ]);
    }
}
