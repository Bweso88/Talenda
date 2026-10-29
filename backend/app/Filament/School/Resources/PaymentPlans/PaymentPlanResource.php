<?php

namespace App\Filament\School\Resources\PaymentPlans;

use App\Filament\School\Resources\PaymentPlans\Pages\CreatePaymentPlan;
use App\Filament\School\Resources\PaymentPlans\Pages\EditPaymentPlan;
use App\Filament\School\Resources\PaymentPlans\Pages\ListPaymentPlans;
use App\Filament\School\Resources\PaymentPlans\Schemas\PaymentPlanForm;
use App\Filament\School\Resources\PaymentPlans\Tables\PaymentPlansTable;
use App\Filament\School\Support\NavigationGroups;
use App\Modules\Finances\Models\PaymentPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentPlanResource extends Resource
{
    protected static ?string $model = PaymentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCE_SETTINGS;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'plans-de-paiement';

    protected static ?string $navigationLabel = 'Plans de paiement';

    protected static ?string $modelLabel = 'plan de paiement';

    protected static ?string $pluralModelLabel = 'plans de paiement';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PaymentPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentPlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentPlans::route('/'),
            'create' => CreatePaymentPlan::route('/nouveau'),
            'edit' => EditPaymentPlan::route('/{record}/modifier'),
        ];
    }
}
