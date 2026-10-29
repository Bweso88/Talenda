<?php

namespace App\Filament\School\Resources\Invoices\Pages;

use App\Filament\School\Resources\Invoices\InvoiceActions;
use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Modules\Finances\Models\Invoice;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return "Facture {$invoice->number}";
    }

    protected function getHeaderActions(): array
    {
        return [
            InvoiceActions::collect(),
            ActionGroup::make([
                InvoiceActions::addFee(),
                InvoiceActions::addDiscount(),
                InvoiceActions::changePlan(),
                InvoiceActions::addAdjustment(),
                InvoiceActions::applyGrid(),
                InvoiceActions::cancelRemaining(),
            ])->label('Modifier la facture')->button(),
        ];
    }
}
