<?php

namespace App\Filament\School\Resources\CashSessions\Tables;

use App\Modules\Finances\Models\CashSession;
use App\Modules\Finances\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cashRegister', 'opener', 'closer'])->withSum(['payments as collected' => fn (Builder $payments) => $payments->where('status', 'valid')], 'amount'))
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('cashRegister.name')->label('Caisse'),
                TextColumn::make('opener.name')->label('Caissier'),
                TextColumn::make('opened_at')->label('Ouverture')->dateTime('d/m/Y H:i'),
                TextColumn::make('closed_at')->label('Clôture')->dateTime('d/m/Y H:i')->placeholder('Ouverte'),
                TextColumn::make('collected')->label('Encaissé (tous moyens)')->alignEnd()
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state))->placeholder(Money::format(0)),
                TextColumn::make('difference')->label('Écart')->alignEnd()->badge()
                    ->getStateUsing(fn (CashSession $record): ?string => $record->difference() === null ? null : Money::format($record->difference()))
                    ->color(fn (CashSession $record): string => match (true) {
                        $record->difference() === null => 'gray',
                        $record->difference() === 0 => 'success',
                        default => 'danger',
                    })
                    ->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
