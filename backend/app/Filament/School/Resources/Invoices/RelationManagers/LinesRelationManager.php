<?php

namespace App\Filament\School\Resources\Invoices\RelationManagers;

use App\Modules\Finances\Models\InvoiceLine;
use App\Modules\Finances\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Détail de la facture, en lecture seule : les modifications passent par
 * les actions de la fiche (nouvelles lignes).
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Détail';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('author'))
            ->paginated(false)
            ->columns([
                TextColumn::make('label')->label('Libellé')->weight('bold'),
                TextColumn::make('kind')->label('Nature')->badge(),
                TextColumn::make('billing')->label('Échéance'),
                TextColumn::make('amount')->label('Montant')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('reason')->label('Motif')->placeholder('—')->wrap(),
                TextColumn::make('author.name')->label('Par')->placeholder('Automatique'),
                TextColumn::make('created_at')->label('Le')->dateTime('d/m/Y H:i'),
            ])
            ->recordTitle(fn (InvoiceLine $record): string => $record->label);
    }
}
