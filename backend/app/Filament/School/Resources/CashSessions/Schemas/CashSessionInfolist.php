<?php

namespace App\Filament\School\Resources\CashSessions\Schemas;

use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\CashSession;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CashSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (?int $amount): string => $amount === null ? '—' : Money::format($amount);

        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->components([
                        TextEntry::make('cashRegister.name')->label('Caisse'),
                        TextEntry::make('opener.name')->label('Ouverte par'),
                        TextEntry::make('opened_at')->label('Ouverture')->dateTime('d/m/Y H:i'),
                        TextEntry::make('closed_at')->label('Clôture')->dateTime('d/m/Y H:i')->placeholder('Encore ouverte'),
                        TextEntry::make('opening_float')->label('Fond de caisse')->formatStateUsing(fn (int $state): string => $money($state)),
                        TextEntry::make('expected_cash')->label('Espèces attendues')
                            ->state(fn (CashSession $record): string => $money($record->expected_cash ?? $record->currentExpectedCash())),
                        TextEntry::make('counted_cash')->label('Espèces comptées')->state(fn (CashSession $record): string => $money($record->counted_cash)),
                        TextEntry::make('difference')->label('Écart')->badge()
                            ->state(fn (CashSession $record): string => $money($record->difference()))
                            ->color(fn (CashSession $record): string => in_array($record->difference(), [null, 0], true) ? 'success' : 'danger'),
                        TextEntry::make('closing_note')->label('Explication')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Totaux par moyen de paiement')
                    ->columns(4)
                    ->components(array_map(
                        fn (PaymentMethod $method): TextEntry => TextEntry::make("total_{$method->value}")
                            ->label($method->getLabel())
                            ->state(fn (CashSession $record): string => $money((int) $record->payments()->where('status', 'valid')->where('method', $method)->sum('amount'))),
                        PaymentMethod::cases(),
                    )),
                Section::make('Encaissements')
                    ->components([
                        RepeatableEntry::make('payment_rows')
                            ->hiddenLabel()
                            ->state(fn (CashSession $record): array => $record->payments()->with('invoice.student')->orderBy('id')->get()
                                ->map(fn (Payment $payment): array => [
                                    'receipt' => $payment->receipt_number,
                                    'student' => $payment->invoice->student->full_name,
                                    'amount' => $money($payment->amount),
                                    'method' => $payment->method->getLabel(),
                                    'status' => $payment->status->getLabel(),
                                ])->all())
                            ->table([
                                TableColumn::make('Reçu'),
                                TableColumn::make('Élève'),
                                TableColumn::make('Montant')->alignEnd(),
                                TableColumn::make('Moyen'),
                                TableColumn::make('Statut'),
                            ])
                            ->schema([
                                TextEntry::make('receipt'),
                                TextEntry::make('student'),
                                TextEntry::make('amount')->alignEnd(),
                                TextEntry::make('method'),
                                TextEntry::make('status'),
                            ]),
                    ]),
            ]);
    }
}
