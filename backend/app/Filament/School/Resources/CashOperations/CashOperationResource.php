<?php

namespace App\Filament\School\Resources\CashOperations;

use App\Filament\School\Resources\CashOperations\Pages\ManageCashOperations;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Models\User;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Finances\Actions\CashDesk;
use App\Modules\Finances\Actions\CashOperations;
use App\Modules\Finances\Enums\CashOperationStatus;
use App\Modules\Finances\Enums\CashOperationType;
use App\Modules\Finances\Models\CashOperation;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\ExpenseCategory;
use App\Modules\Finances\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Opérations de caisse (docs/TRESORERIE.md) : dépenses, salaires, avances,
 * virements ; validation, justification, réception, annulation.
 */
class CashOperationResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = CashOperation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCES;

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'tresorerie';

    protected static ?string $modelLabel = 'opération de caisse';

    protected static ?string $pluralModelLabel = 'opérations de caisse';

    protected static ?string $navigationLabel = 'Trésorerie';

    public static function getNavigationBadge(): ?string
    {
        if (! (Filament::auth()->user()?->can('treasury.approve') ?? false)) {
            return null;
        }

        $pending = CashOperation::query()->where('status', CashOperationStatus::PendingApproval)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => self::visibleTo($query, self::user())
                ->with(['cashRegister', 'destinationRegister', 'expenseCategory', 'staffMember', 'creator']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Aucune opération de caisse')
            ->emptyStateDescription('Dépenses, salaires, avances et virements entre caisses apparaissent ici.')
            ->columns([
                TextColumn::make('number')->label('N°')->weight('bold')->searchable()
                    ->description(fn (CashOperation $record): string => $record->occurred_on->format('d/m/Y')),
                TextColumn::make('description')->label('Opération')->wrap()->searchable()
                    ->description(fn (CashOperation $record): string => $record->type->getLabel().($record->type === CashOperationType::Advance ? ' — '.$record->beneficiaryName() : '')),
                TextColumn::make('cashRegister.name')->label('Caisse')
                    ->description(fn (CashOperation $record): ?string => $record->destinationRegister === null ? null : '→ '.$record->destinationRegister->name),
                TextColumn::make('amount')->label('Montant')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (CashOperation $record): ?string => self::outcome($record)),
                TextColumn::make('status')->label('État')->badge(),
                TextColumn::make('creator.name')->label('Saisie par')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Type')->options(CashOperationType::class),
                SelectFilter::make('status')->label('État')->options(CashOperationStatus::class),
                SelectFilter::make('cash_register_id')->label('Caisse')->relationship('cashRegister', 'name'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Valider')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (CashOperation $record): bool => $record->status === CashOperationStatus::PendingApproval && self::user()->can('treasury.approve') && $record->created_by !== self::user()->id)
                    ->requiresConfirmation()
                    ->modalDescription(fn (CashOperation $record): string => "{$record->type->getLabel()} de ".Money::format($record->amount)." : {$record->description}. L'argent sort de « {$record->cashRegister->name} ».")
                    ->action(fn (CashOperation $record) => self::guard(fn () => app(CashOperations::class)->approve($record, self::user()), 'Opération validée.')),
                Action::make('reject')
                    ->label('Refuser')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (CashOperation $record): bool => $record->status === CashOperationStatus::PendingApproval && self::user()->can('treasury.approve') && $record->created_by !== self::user()->id)
                    ->schema([TextInput::make('reason')->label('Motif du refus')->required()->maxLength(255)])
                    ->action(fn (CashOperation $record, array $data) => self::guard(fn () => app(CashOperations::class)->reject($record, $data['reason'], self::user()), 'Demande refusée.')),
                Action::make('settle')
                    ->label('Justifier')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->visible(fn (CashOperation $record): bool => $record->status === CashOperationStatus::Outstanding && self::user()->can('treasury.operate'))
                    ->modalHeading(fn (CashOperation $record): string => 'Retour de l\'avance '.$record->number)
                    ->modalDescription(fn (CashOperation $record): string => Money::format($record->amount)." remis à {$record->beneficiaryName()} : {$record->description}. Saisissez la facture et la monnaie rendue (achat annulé : tout rendu, dépensé 0).")
                    ->fillForm(fn (CashOperation $record): array => ['spent' => $record->amount, 'returned' => 0, 'expense_category_id' => $record->expense_category_id])
                    ->schema([
                        TextInput::make('spent')->label('Dépensé (facture)')->numeric()->integer()->minValue(0)->suffix('FCFA')->required(),
                        TextInput::make('returned')->label('Monnaie rendue')->numeric()->integer()->minValue(0)->suffix('FCFA')->required(),
                        Select::make('expense_category_id')->label('Catégorie')->options(fn (): array => self::categories())->required(),
                        TextInput::make('note')->label('Explication (achat plus cher, annulé…)')->maxLength(255),
                    ])
                    ->action(fn (CashOperation $record, array $data) => self::guard(fn () => app(CashOperations::class)->settleAdvance(
                        $record,
                        (int) $data['spent'],
                        (int) $data['returned'],
                        self::user(),
                        $data['note'] ?? null,
                        ExpenseCategory::query()->find($data['expense_category_id']),
                    ), 'Avance justifiée.')),
                Action::make('receive')
                    ->label('Réceptionner')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->color('success')
                    ->visible(fn (CashOperation $record): bool => $record->status === CashOperationStatus::InTransit && self::canUse($record->destinationRegister))
                    ->modalDescription(fn (CashOperation $record): string => Money::format($record->amount)." envoyés par « {$record->cashRegister->name} ». Comptez l'argent reçu.")
                    ->fillForm(fn (CashOperation $record): array => ['received' => $record->amount])
                    ->schema([
                        TextInput::make('received')->label('Montant compté')->numeric()->integer()->minValue(0)->suffix('FCFA')->required(),
                        TextInput::make('note')->label('Explication de l\'écart éventuel')->maxLength(255),
                    ])
                    ->action(fn (CashOperation $record, array $data) => self::guard(fn () => app(CashOperations::class)->receiveTransfer($record, (int) $data['received'], self::user(), $data['note'] ?? null), 'Virement reçu.')),
                Action::make('reverse')
                    ->label('Annuler')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (CashOperation $record): bool => self::canReverse($record))
                    ->modalDescription('L\'argent revient dans la caisse ; l\'opération reste visible, marquée annulée, avec votre nom et le motif.')
                    ->schema([TextInput::make('reason')->label('Motif')->required()->maxLength(255)])
                    ->action(fn (CashOperation $record, array $data) => self::guard(fn () => app(CashOperations::class)->reverse($record, $data['reason'], self::user()), 'Opération annulée.')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCashOperations::route('/'),
        ];
    }

    /**
     * La direction voit tout ; un caissier voit ce qu'il a saisi et ce qui
     * concerne la caisse qu'il tient.
     *
     * @param  Builder<CashOperation>  $query
     * @return Builder<CashOperation>
     */
    public static function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('treasury.approve') || $user->can('treasury.manage')) {
            return $query;
        }

        $registerId = CashDesk::openSessionOf($user)?->cash_register_id;

        return $query->where(fn (Builder $query) => $query
            ->where('created_by', $user->id)
            ->when($registerId !== null, fn (Builder $query) => $query
                ->orWhere('cash_register_id', $registerId)
                ->orWhere('destination_register_id', $registerId)));
    }

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return ExpenseCategory::query()->where('is_active', true)->orderBy('position')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function registers(): array
    {
        return CashOperations::registersFor(self::user())
            ->mapWithKeys(fn (CashRegister $register): array => [$register->id => "{$register->name} (".Money::format($register->balance()).')'])
            ->all();
    }

    /**
     * Caisse proposée : la seule disponible, s'il n'y en a qu'une.
     */
    public static function defaultRegister(): ?int
    {
        $registers = CashOperations::registersFor(self::user());

        return $registers->count() === 1 ? $registers->first()?->id : null;
    }

    public static function registerField(string $name = 'cash_register_id', string $label = 'Caisse'): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn (): array => self::registers())
            ->default(fn (): ?int => self::defaultRegister())
            ->helperText(fn (): ?string => self::registers() === [] ? 'Ouvrez d\'abord votre caisse (page Caisse).' : null)
            ->live()
            ->required();
    }

    public static function destinationField(): Select
    {
        return Select::make('destination_register_id')
            ->label('Vers la caisse')
            ->options(fn (Get $get): array => CashRegister::query()
                ->where('is_active', true)
                ->whereKeyNot($get('cash_register_id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->required();
    }

    private static function canUse(?CashRegister $register): bool
    {
        $user = self::user();

        return $register !== null && ($user->can('treasury.manage') || ($user->can('treasury.operate') && CashDesk::openSessionOf($user)?->cash_register_id === $register->id));
    }

    private static function canReverse(CashOperation $record): bool
    {
        $user = self::user();

        return match (true) {
            $record->type === CashOperationType::Transfer => $record->status === CashOperationStatus::InTransit && ($record->created_by === $user->id || $user->can('treasury.approve')),
            $record->type === CashOperationType::Advance => false,
            default => $record->status === CashOperationStatus::Done && $user->can('treasury.approve') && $record->created_by !== $user->id,
        };
    }

    /**
     * Ce qui s'est passé ensuite : refus, justification, réception, annulation.
     */
    private static function outcome(CashOperation $record): ?string
    {
        return match ($record->status) {
            CashOperationStatus::Settled => 'Dépensé '.Money::format((int) $record->spent_amount).', rendu '.Money::format((int) $record->returned_amount),
            CashOperationStatus::Received => $record->received_amount === $record->amount ? null : 'Reçu '.Money::format((int) $record->received_amount),
            CashOperationStatus::Rejected => $record->rejection_reason,
            CashOperationStatus::Reversed, CashOperationStatus::Cancelled => $record->reversal_reason,
            default => null,
        };
    }

    public static function guard(callable $callback, string $successMessage): void
    {
        try {
            $result = $callback();
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $pending = $result instanceof CashOperation && $result->status === CashOperationStatus::PendingApproval;

        Notification::make()
            ->success()
            ->title($pending ? "{$result->number} enregistrée : au-delà du seuil, elle attend la validation de la direction." : $successMessage)
            ->send();
    }

    public static function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
