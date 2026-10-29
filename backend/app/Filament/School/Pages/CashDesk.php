<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Support\FinanceLinks;
use App\Filament\School\Support\NavigationGroups;
use App\Models\User;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Finances\Actions\CashDesk as CashDeskAction;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\CashSession;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Poste de caisse : ouverture de session, recherche de l'élève, situation
 * de sa facture, encaissement et reçu, clôture avec comptage.
 *
 * @property-read Schema $form
 */
class CashDesk extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCES;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'caisse';

    protected static ?string $title = 'Caisse';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * Facture à présélectionner (bouton « Encaisser » d'une fiche facture).
     */
    #[Url(as: 'facture')]
    public ?string $invoiceUuid = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('payment.record') ?? false;
    }

    public function mount(): void
    {
        $invoice = filled($this->invoiceUuid)
            ? Invoice::query()->where('uuid', $this->invoiceUuid)->first()
            : null;

        $this->form->fill([
            'invoice_id' => $invoice?->id,
            'method' => PaymentMethod::Cash->value,
            'paid_on' => now()->toDateString(),
            'amount' => $invoice === null ? null : $this->suggestedAmount($invoice),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->components([
                    Text::make(fn (): string => $this->sessionSummary())
                        ->color(fn (): string => $this->session() === null ? 'warning' : 'success'),
                ]),
            EmbeddedSchema::make('form'),
            Section::make('Mes encaissements du jour')
                ->components([EmbeddedTable::make()]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Encaissement')
                    ->columns(3)
                    ->components([
                        Select::make('invoice_id')
                            ->label('Élève')
                            ->placeholder('Nom, prénom ou matricule')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->searchInvoices($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->invoiceLabel(Invoice::query()->with(['student', 'enrollment.schoolClass', 'schoolYear'])->find($value)))
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $invoice = $state === null ? null : Invoice::query()->find($state);
                                $set('amount', $invoice === null ? null : $this->suggestedAmount($invoice));
                            })
                            ->required()
                            ->columnSpanFull(),
                        Text::make(fn (Get $get): string => $this->invoiceSummary($get('invoice_id')))
                            ->visible(fn (Get $get): bool => filled($get('invoice_id')))
                            ->columnSpanFull(),
                        TextInput::make('amount')
                            ->label('Montant reçu')
                            ->numeric()->integer()->minValue(1)
                            ->suffix('FCFA')
                            ->required(),
                        Select::make('method')
                            ->label('Moyen de paiement')
                            ->options(PaymentMethod::class)
                            ->live()
                            ->required(),
                        TextInput::make('reference')
                            ->label('Référence (n° de transaction, de chèque…)')
                            ->required(fn (Get $get): bool => $get('method') !== null && $get('method') !== PaymentMethod::Cash->value && $get('method') !== PaymentMethod::Cash)
                            ->maxLength(100),
                        TextInput::make('payer_name')
                            ->label('Versé par')
                            ->placeholder('Nom de la personne au guichet')
                            ->maxLength(150),
                        DatePicker::make('paid_on')
                            ->label('Date du paiement')
                            ->maxDate(now())
                            ->required(),
                        Actions::make([
                            Action::make('record')
                                ->label('Encaisser')
                                ->icon(Heroicon::OutlinedCheck)
                                ->color('success')
                                ->action(fn () => $this->recordPayment()),
                        ])->verticallyAlignEnd(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query()
                ->with('invoice.student')
                ->where('recorded_by', Filament::auth()->id())
                ->whereDate('created_at', today()))
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Aucun encaissement aujourd\'hui')
            ->columns([
                TextColumn::make('receipt_number')->label('Reçu')->weight('bold'),
                TextColumn::make('created_at')->label('Heure')->dateTime('H:i'),
                TextColumn::make('invoice.student.full_name')->label('Élève'),
                TextColumn::make('amount')->label('Montant')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('method')->label('Moyen'),
                TextColumn::make('status')->label('Statut')->badge(),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label('Reçu')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Payment $record): string => FinanceLinks::receiptUrl($record), shouldOpenInNewTab: true),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('openSession')
                ->label('Ouvrir la caisse')
                ->icon(Heroicon::OutlinedLockOpen)
                ->visible(fn (): bool => $this->session() === null)
                ->schema([
                    Select::make('cash_register_id')
                        ->label('Caisse')
                        ->options(fn (): array => CashRegister::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?int => CashRegister::query()->where('is_active', true)->count() === 1 ? CashRegister::query()->where('is_active', true)->value('id') : null)
                        ->required(),
                    TextInput::make('opening_float')
                        ->label('Fond de caisse (espèces au départ)')
                        ->numeric()->integer()->minValue(0)->default(0)->suffix('FCFA')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->guard(fn () => app(CashDeskAction::class)->open(
                        CashRegister::query()->findOrFail($data['cash_register_id']),
                        (int) $data['opening_float'],
                        $this->user(),
                    ), 'Caisse ouverte.');
                }),
            Action::make('closeSession')
                ->label('Clôturer la caisse')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('danger')
                ->visible(fn (): bool => $this->session() !== null)
                ->modalDescription(fn (): string => 'Espèces attendues : '.Money::format($this->session()?->currentExpectedCash() ?? 0).'. Comptez la caisse et saisissez le montant réel.')
                ->schema([
                    TextInput::make('counted_cash')
                        ->label('Espèces comptées')
                        ->numeric()->integer()->minValue(0)->suffix('FCFA')
                        ->required(),
                    TextInput::make('closing_note')
                        ->label('Explication de l\'écart éventuel')
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $session = $this->session();

                    if ($session === null) {
                        return;
                    }

                    $this->guard(fn () => app(CashDeskAction::class)->close($session, (int) $data['counted_cash'], $data['closing_note'] ?? null, $this->user()), 'Caisse clôturée.');
                }),
        ];
    }

    public function recordPayment(): void
    {
        $state = $this->form->getState();

        try {
            $payment = app(RecordPayment::class)->handle(
                Invoice::query()->findOrFail($state['invoice_id']),
                (int) $state['amount'],
                $state['method'] instanceof PaymentMethod ? $state['method'] : PaymentMethod::from($state['method']),
                $this->user(),
                $state['reference'] ?? null,
                $state['payer_name'] ?? null,
                Carbon::parse($state['paid_on']),
            );
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Encaissé : '.Money::format($payment->amount)." — reçu {$payment->receipt_number}")
            ->actions([
                Action::make('print')
                    ->label('Imprimer le reçu')
                    ->url(FinanceLinks::receiptUrl($payment), shouldOpenInNewTab: true),
            ])
            ->persistent()
            ->send();

        $this->form->fill([
            'method' => PaymentMethod::Cash->value,
            'paid_on' => now()->toDateString(),
        ]);
    }

    private function session(): ?CashSession
    {
        return CashDeskAction::openSessionOf($this->user());
    }

    private function sessionSummary(): string
    {
        $session = $this->session();

        if ($session === null) {
            return 'Votre caisse est fermée : ouvrez-la pour encaisser des espèces. Les paiements par mobile money, virement ou chèque restent possibles.';
        }

        return "{$session->cashRegister->name} ouverte depuis {$session->opened_at->format('H:i')} — espèces attendues en caisse : ".Money::format($session->currentExpectedCash()).'.';
    }

    /**
     * @return array<int, string>
     */
    private function searchInvoices(string $search): array
    {
        $term = '%'.mb_strtolower(trim($search)).'%';

        return Invoice::query()
            ->with(['student', 'enrollment.schoolClass', 'schoolYear'])
            ->whereHas('schoolYear', fn (Builder $query) => $query->where('status', '!=', SchoolYearStatus::Draft))
            ->whereHas('student', fn (Builder $query) => $query
                ->whereRaw('lower(last_name) like ?', [$term])
                ->orWhereRaw('lower(first_names) like ?', [$term])
                ->orWhereRaw('lower(registration_number) like ?', [$term]))
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Invoice $invoice): array => [$invoice->id => (string) $this->invoiceLabel($invoice)])
            ->all();
    }

    private function invoiceLabel(?Invoice $invoice): ?string
    {
        if ($invoice === null) {
            return null;
        }

        return "{$invoice->student->full_name} ({$invoice->student->registration_number}) — {$invoice->enrollment->schoolClass->name}, {$invoice->schoolYear->name}";
    }

    private function invoiceSummary(mixed $invoiceId): string
    {
        $invoice = filled($invoiceId) ? Invoice::query()->find($invoiceId) : null;

        if ($invoice === null) {
            return '';
        }

        $statement = $invoice->statement();
        $next = $statement->nextDue();

        return 'Total '.Money::format($statement->total)
            .' · payé '.Money::format($statement->paid)
            .' · reste '.Money::format(max(0, $statement->balance()))
            .($statement->overdue() > 0 ? ' · EN RETARD : '.Money::format($statement->overdue()) : ' · à jour')
            .($next === null ? '' : " · prochaine échéance : {$next->label} ({$next->dueOn->format('d/m/Y')}), ".Money::format($next->remaining()));
    }

    /**
     * Montant proposé : le retard s'il y en a, sinon la prochaine échéance.
     */
    private function suggestedAmount(Invoice $invoice): ?int
    {
        $statement = $invoice->statement();

        if ($statement->overdue() > 0) {
            return $statement->overdue();
        }

        return $statement->nextDue()?->remaining();
    }

    private function guard(callable $callback, string $successMessage): void
    {
        try {
            $callback();
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successMessage)->send();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
