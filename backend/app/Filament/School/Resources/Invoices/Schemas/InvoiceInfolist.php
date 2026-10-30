<?php

namespace App\Filament\School\Resources\Invoices\Schemas;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Filament\School\Resources\Students\StudentResource;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Support\Money;
use App\Modules\Finances\Support\StatementInstallment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (int $amount): string => Money::format($amount);
        $statement = fn (Invoice $record) => InvoiceResource::statementOf($record);

        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->components([
                        TextEntry::make('student.full_name')
                            ->label('Élève')
                            ->weight('bold')
                            ->url(fn (Invoice $record): string => StudentResource::getUrl('edit', ['record' => $record->student_id])),
                        TextEntry::make('enrollment.schoolClass.name')->label('Classe'),
                        TextEntry::make('schoolYear.name')->label('Année'),
                        TextEntry::make('paymentPlan.name')->label('Plan de paiement')->placeholder('Aucun (paiement à l\'inscription)'),
                    ]),
                Section::make('Situation')
                    ->columns(4)
                    ->components([
                        TextEntry::make('total')->label('Total de l\'année')->state(fn (Invoice $record): string => $money($statement($record)->total)),
                        TextEntry::make('paid')->label('Payé')->state(fn (Invoice $record): string => $money($statement($record)->paid))->color('success'),
                        TextEntry::make('balance')->label('Reste à payer')->weight('bold')
                            ->state(fn (Invoice $record): string => $money(max(0, $statement($record)->balance()))),
                        TextEntry::make('overdue')->label('En retard')->badge()
                            ->state(fn (Invoice $record): string => $statement($record)->overdue() > 0 ? $money($statement($record)->overdue()) : 'À jour')
                            ->color(fn (Invoice $record): string => $statement($record)->overdue() > 0 ? 'danger' : 'success'),
                    ]),
                Section::make('Échéancier')
                    ->description('Les paiements sont affectés aux échéances les plus anciennes.')
                    ->components([
                        RepeatableEntry::make('schedule')
                            ->hiddenLabel()
                            ->state(fn (Invoice $record): array => array_map(
                                fn (StatementInstallment $installment): array => [
                                    'label' => $installment->label,
                                    'due_on' => $installment->dueOn->format('d/m/Y'),
                                    'amount' => $money($installment->amount),
                                    'paid' => $money($installment->paid),
                                    'status' => $installment->statusLabel(),
                                ],
                                $statement($record)->installments,
                            ))
                            ->table([
                                TableColumn::make('Échéance'),
                                TableColumn::make('Date limite'),
                                TableColumn::make('Montant')->alignEnd(),
                                TableColumn::make('Payé')->alignEnd(),
                                TableColumn::make('Situation'),
                            ])
                            ->schema([
                                TextEntry::make('label'),
                                TextEntry::make('due_on'),
                                TextEntry::make('amount')->alignEnd(),
                                TextEntry::make('paid')->alignEnd(),
                                TextEntry::make('status')->badge()->color(fn (string $state): string => match (true) {
                                    $state === 'Payé' => 'success',
                                    str_starts_with($state, 'En retard') => 'danger',
                                    $state === 'Partiel' => 'warning',
                                    default => 'gray',
                                }),
                            ]),
                    ]),
            ]);
    }
}
