<?php

namespace App\Filament\School\Resources\PaymentPlans\Pages;

use App\Filament\School\Resources\PaymentPlans\PaymentPlanResource;
use App\Modules\Finances\Models\PaymentPlan;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentPlan extends EditRecord
{
    protected static string $resource = PaymentPlanResource::class;

    protected ?string $subheading = 'Modifier les échéances recalcule immédiatement l\'échéancier des familles qui ont choisi ce plan. Les paiements déjà faits restent acquis.';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var PaymentPlan $plan */
        $plan = $this->getRecord();

        if ($data['is_default'] ?? false) {
            PaymentPlan::query()->where('school_year_id', $plan->school_year_id)->whereKeyNot($plan->id)->update(['is_default' => false]);
        }

        return $data;
    }
}
