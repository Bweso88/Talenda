<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Support\Money;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Situation financière de l'élève, année par année.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Scolarité payée';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Filament::auth()->user()?->can('finance.view') ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $statement = fn (Invoice $record) => InvoiceResource::statementOf($record);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['schoolYear', 'enrollment', 'paymentPlan.installments', 'lines', 'payments']))
            ->paginated(false)
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('schoolYear.name')->label('Année')->weight('bold'),
                TextColumn::make('number')->label('Facture'),
                TextColumn::make('total')->label('Total')->alignEnd()->getStateUsing(fn (Invoice $record): string => Money::format($statement($record)->total)),
                TextColumn::make('paid')->label('Payé')->alignEnd()->getStateUsing(fn (Invoice $record): string => Money::format($statement($record)->paid)),
                TextColumn::make('balance')->label('Reste')->alignEnd()->getStateUsing(fn (Invoice $record): string => Money::format(max(0, $statement($record)->balance()))),
                TextColumn::make('overdue')->label('En retard')->badge()
                    ->getStateUsing(fn (Invoice $record): string => $statement($record)->overdue() > 0 ? Money::format($statement($record)->overdue()) : 'À jour')
                    ->color(fn (Invoice $record): string => $statement($record)->overdue() > 0 ? 'danger' : 'success'),
            ])
            ->recordActions([
                Action::make('open')->label('Ouvrir')->url(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
