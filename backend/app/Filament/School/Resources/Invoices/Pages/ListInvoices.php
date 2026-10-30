<?php

namespace App\Filament\School\Resources\Invoices\Pages;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Finances\Actions\ApplyFeeGrid;
use App\Modules\Finances\Models\Invoice;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected ?string $subheading = 'Une facture est créée automatiquement à chaque inscription, selon la grille tarifaire.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('applyGrid')
                ->label('Appliquer la grille aux factures')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Ajoute aux factures de l\'année ouverte les frais obligatoires de la grille qui leur manquent. Rien n\'est modifié ni retiré.')
                ->visible(fn (): bool => Filament::auth()->user()?->can('finance.manage') ?? false)
                ->action(function (): void {
                    $added = 0;

                    Invoice::query()
                        ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                        ->with('enrollment.schoolClass')
                        ->each(function (Invoice $invoice) use (&$added): void {
                            $added += app(ApplyFeeGrid::class)->handle($invoice, Filament::auth()->user());
                        });

                    Notification::make()->success()->title($added === 0 ? 'Toutes les factures sont à jour.' : "{$added} frais ajouté(s).")->send();
                }),
        ];
    }
}
