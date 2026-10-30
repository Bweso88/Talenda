<?php

namespace App\Filament\School\Resources\Invoices\Tables;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        $money = fn (int $amount): string => Money::format($amount);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'student', 'enrollment.schoolClass', 'paymentPlan.installments', 'lines', 'payments',
            ]))
            ->defaultSort('number', 'desc')
            ->columns([
                TextColumn::make('number')->label('Facture')->searchable()->sortable(),
                TextColumn::make('student.full_name')
                    ->label('Élève')
                    ->weight('bold')
                    ->searchable(['last_name', 'first_names', 'registration_number']),
                TextColumn::make('enrollment.schoolClass.name')->label('Classe'),
                TextColumn::make('paymentPlan.name')->label('Plan')->placeholder('—'),
                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->getStateUsing(fn (Invoice $record): string => $money(InvoiceResource::statementOf($record)->total)),
                TextColumn::make('paid')
                    ->label('Payé')
                    ->alignEnd()
                    ->getStateUsing(fn (Invoice $record): string => $money(InvoiceResource::statementOf($record)->paid)),
                TextColumn::make('balance')
                    ->label('Reste à payer')
                    ->alignEnd()
                    ->weight('bold')
                    ->getStateUsing(fn (Invoice $record): string => $money(max(0, InvoiceResource::statementOf($record)->balance()))),
                TextColumn::make('overdue')
                    ->label('En retard')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (Invoice $record): string => InvoiceResource::statementOf($record)->overdue() > 0 ? 'danger' : 'success')
                    ->getStateUsing(fn (Invoice $record): string => InvoiceResource::statementOf($record)->overdue() > 0
                        ? $money(InvoiceResource::statementOf($record)->overdue())
                        : 'À jour'),
            ])
            ->filters([
                SelectFilter::make('school_year_id')
                    ->label('Année scolaire')
                    ->relationship('schoolYear', 'name', fn (Builder $query) => $query->orderByDesc('starts_on'))
                    ->default(fn (): ?int => SchoolYear::query()->open()->value('id')),
                SelectFilter::make('class')
                    ->label('Classe')
                    ->options(fn (): array => SchoolClass::query()
                        ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $classId) => $query->whereHas('enrollment', fn (Builder $enrollments) => $enrollments->where('school_class_id', $classId)),
                    )),
            ])
            ->recordActions([
                ViewAction::make()->label('Ouvrir'),
            ]);
    }
}
