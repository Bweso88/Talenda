<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Resources;

use App\Modules\Finances\Enums\PaymentStatus;
use App\Modules\Finances\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'receipt_number' => $this->receipt_number,
            'paid_on' => $this->paid_on->toDateString(),
            'amount' => $this->amount,
            'method' => $this->method->value,
            'method_label' => $this->method->getLabel(),
            'status' => $this->status->value,
            'has_receipt' => $this->status === PaymentStatus::Valid,
        ];
    }
}
