<?php

namespace App\Filament\School\Resources\Invoices;

use App\Filament\School\Resources\Invoices\Pages\ListInvoices;
use App\Filament\School\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\School\Resources\Invoices\RelationManagers\LinesRelationManager;
use App\Filament\School\Resources\Invoices\RelationManagers\PaymentsRelationManager;
use App\Filament\School\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\School\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\School\Support\NavigationGroups;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Support\InvoiceStatement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use WeakMap;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCES;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'factures';

    protected static ?string $modelLabel = 'facture';

    protected static ?string $recordTitleAttribute = 'number';

    /**
     * Situation calculée une seule fois par facture et par requête, même si
     * plusieurs colonnes l'affichent.
     *
     * @var WeakMap<Invoice, InvoiceStatement>|null
     */
    private static ?WeakMap $statements = null;

    public static function statementOf(Invoice $invoice): InvoiceStatement
    {
        self::$statements ??= new WeakMap;

        return self::$statements[$invoice] ??= $invoice->statement();
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
