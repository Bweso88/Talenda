<?php

namespace App\Filament\School\Resources\PaymentPlans\Pages;

use App\Filament\School\Resources\PaymentPlans\PaymentPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentPlans extends ListRecords
{
    protected static string $resource = PaymentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
