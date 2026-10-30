<?php

namespace App\Filament\School\Resources\CashOperations\Pages;

use App\Filament\School\Resources\CashOperations\CashOperationResource as Treasury;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Finances\Actions\CashOperations;
use App\Modules\Finances\Enums\CashOperationStatus;
use App\Modules\Finances\Models\CashOperation;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\ExpenseCategory;
use App\Modules\Finances\Models\TreasurySetting;
use App\Modules\Finances\Support\JournalExport;
use App\Modules\Finances\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageCashOperations extends ManageRecords
{
    protected static string $resource = Treasury::class;

    protected static ?string $title = 'Trésorerie';

    /**
     * Soldes des caisses et points en attente, en tête de page.
     */
    public function getSubheading(): ?string
    {
        $user = Treasury::user();
        $registers = $user->can('treasury.manage')
            ? CashRegister::query()->where('is_active', true)->orderBy('name')->get()
            : CashOperations::registersFor($user);

        $balances = $registers->map(fn (CashRegister $register): string => "{$register->name} : ".Money::format($register->balance()))->implode(' · ');
        $pending = Treasury::visibleTo(CashOperation::query(), $user)->where('status', CashOperationStatus::PendingApproval)->count();
        $advances = Treasury::visibleTo(CashOperation::query(), $user)->where('status', CashOperationStatus::Outstanding)->count();
        $transit = Treasury::visibleTo(CashOperation::query(), $user)->where('status', CashOperationStatus::InTransit)->count();

        return collect([
            $balances === '' ? 'Ouvrez votre caisse (page Caisse) pour enregistrer une dépense.' : "Soldes — {$balances}",
            $pending > 0 ? "{$pending} à valider" : null,
            $advances > 0 ? "{$advances} avance(s) à justifier" : null,
            $transit > 0 ? "{$transit} virement(s) en route" : null,
        ])->filter()->implode(' — ');
    }

    protected function getHeaderActions(): array
    {
        $canOperate = fn (): bool => Treasury::user()->can('treasury.operate');

        return [
            Action::make('expense')
                ->label('Dépense')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->visible($canOperate)
                ->modalDescription(fn (): string => 'Au-delà de '.Money::format(TreasurySetting::current()->approval_threshold).', la dépense attend la validation de la direction.')
                ->schema([
                    Treasury::registerField(),
                    Select::make('expense_category_id')->label('Catégorie')->options(fn (): array => Treasury::categories())->required(),
                    TextInput::make('amount')->label('Montant')->numeric()->integer()->minValue(1)->suffix('FCFA')->required(),
                    TextInput::make('description')->label('Objet')->placeholder('Facture LCDE de novembre')->required()->maxLength(255),
                    TextInput::make('reference')->label('N° de facture ou de pièce')->maxLength(100),
                    DatePicker::make('occurred_on')->label('Date')->default(now())->maxDate(now())->required(),
                ])
                ->action(fn (array $data) => Treasury::guard(fn () => app(CashOperations::class)->expense(
                    CashRegister::query()->findOrFail($data['cash_register_id']),
                    ExpenseCategory::query()->findOrFail($data['expense_category_id']),
                    (int) $data['amount'],
                    $data['description'],
                    Treasury::user(),
                    $data['reference'] ?? null,
                    Carbon::parse($data['occurred_on']),
                ), 'Dépense enregistrée.')),
            Action::make('salary')
                ->label('Salaire')
                ->icon(Heroicon::OutlinedUserCircle)
                ->visible($canOperate)
                ->schema([
                    Treasury::registerField(),
                    Select::make('staff_member_id')->label('Membre du personnel')
                        ->options(fn (): array => StaffMember::query()->active()->orderBy('last_name')->get()->pluck('full_name', 'id')->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('period')->label('Mois payé')->placeholder('2026-11')->default(now()->format('Y-m'))->regex('/^\d{4}-(0[1-9]|1[0-2])$/')->required(),
                    TextInput::make('amount')->label('Montant net versé')->numeric()->integer()->minValue(1)->suffix('FCFA')->required(),
                    TextInput::make('description')->label('Précision (second versement, rappel…)')->maxLength(255),
                ])
                ->action(fn (array $data) => Treasury::guard(fn () => app(CashOperations::class)->salary(
                    CashRegister::query()->findOrFail($data['cash_register_id']),
                    StaffMember::query()->findOrFail($data['staff_member_id']),
                    $data['period'],
                    (int) $data['amount'],
                    Treasury::user(),
                    blank($data['description'] ?? null) ? null : $data['description'],
                ), 'Salaire payé.')),
            Action::make('advance')
                ->label('Avance à justifier')
                ->icon(Heroicon::OutlinedHandRaised)
                ->visible($canOperate)
                ->modalDescription('Argent remis pour un achat : la personne revient avec la facture et la monnaie.')
                ->schema([
                    Treasury::registerField(),
                    Select::make('staff_member_id')->label('Remis à (personnel)')
                        ->options(fn (): array => StaffMember::query()->active()->orderBy('last_name')->get()->pluck('full_name', 'id')->all())
                        ->searchable()
                        ->live(),
                    TextInput::make('beneficiary')->label('… ou autre personne')
                        ->required(fn (Get $get): bool => blank($get('staff_member_id')))
                        ->maxLength(150),
                    Select::make('expense_category_id')->label('Pour quel achat')->options(fn (): array => Treasury::categories())->required(),
                    TextInput::make('amount')->label('Montant remis')->numeric()->integer()->minValue(1)->suffix('FCFA')->required(),
                    TextInput::make('description')->label('Objet')->placeholder('Craies et marqueurs')->required()->maxLength(255),
                ])
                ->action(fn (array $data) => Treasury::guard(fn () => app(CashOperations::class)->advance(
                    CashRegister::query()->findOrFail($data['cash_register_id']),
                    ExpenseCategory::query()->findOrFail($data['expense_category_id']),
                    (int) $data['amount'],
                    $data['description'],
                    Treasury::user(),
                    blank($data['staff_member_id'] ?? null) ? null : StaffMember::query()->find($data['staff_member_id']),
                    $data['beneficiary'] ?? null,
                ), 'Avance remise.')),
            Action::make('transfer')
                ->label('Virement')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->visible($canOperate)
                ->modalDescription('Versement à la caisse centrale, dépôt en banque… L\'argent est « en route » jusqu\'à ce que la caisse qui reçoit le compte.')
                ->schema([
                    Treasury::registerField('cash_register_id', 'De la caisse'),
                    Treasury::destinationField(),
                    TextInput::make('amount')->label('Montant')->numeric()->integer()->minValue(1)->suffix('FCFA')->required(),
                    TextInput::make('description')->label('Objet')->maxLength(255),
                ])
                ->action(fn (array $data) => Treasury::guard(fn () => app(CashOperations::class)->transfer(
                    CashRegister::query()->findOrFail($data['cash_register_id']),
                    CashRegister::query()->findOrFail($data['destination_register_id']),
                    (int) $data['amount'],
                    Treasury::user(),
                    blank($data['description'] ?? null) ? null : $data['description'],
                ), 'Virement envoyé.')),
            ActionGroup::make([
                Action::make('exportJournal')
                    ->label('Exporter le journal comptable')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->modalDescription('Écritures SYSCOHADA de la période (encaissements, dépenses, salaires, avances, virements, écarts), à transmettre au comptable. Fichier lisible par Excel.')
                    ->schema([
                        DatePicker::make('from')->label('Du')->default(now()->startOfMonth())->required(),
                        DatePicker::make('to')->label('Au')->default(now())->required(),
                    ])
                    ->action(function (array $data): StreamedResponse {
                        $from = Carbon::parse($data['from']);
                        $to = Carbon::parse($data['to']);
                        $csv = app(JournalExport::class)->csv($from, $to);

                        return response()->streamDownload(fn () => print ($csv), "journal-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
                Action::make('threshold')
                    ->label('Seuil de validation')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->fillForm(fn (): array => ['approval_threshold' => TreasurySetting::current()->approval_threshold])
                    ->schema([
                        TextInput::make('approval_threshold')
                            ->label('Validation par la direction au-delà de')
                            ->helperText('Dépenses, salaires et avances. 0 : tout est validé par la direction.')
                            ->numeric()->integer()->minValue(0)->suffix('FCFA')->required(),
                    ])
                    ->action(function (array $data): void {
                        TreasurySetting::current()->update(['approval_threshold' => (int) $data['approval_threshold']]);
                        Notification::make()->success()->title('Seuil enregistré.')->send();
                    }),
            ])
                ->label('Comptabilité')
                ->icon(Heroicon::OutlinedCalculator)
                ->button()
                ->color('gray')
                ->visible(fn (): bool => Treasury::user()->can('treasury.manage')),
        ];
    }
}
