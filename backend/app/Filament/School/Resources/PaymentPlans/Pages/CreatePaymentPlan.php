<?php

namespace App\Filament\School\Resources\PaymentPlans\Pages;

use App\Filament\School\Resources\PaymentPlans\PaymentPlanResource;
use App\Modules\Finances\Models\PaymentPlan;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentPlan extends CreateRecord
{
    protected static string $resource = PaymentPlanResource::class;

    /**
     * Un seul plan par défaut par année : l'ancien cède sa place.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['is_default'] ?? false) {
            PaymentPlan::query()->where('school_year_id', $data['school_year_id'])->update(['is_default' => false]);
        }

        return $data;
    }
}
