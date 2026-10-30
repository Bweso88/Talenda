<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Resources;

use App\Modules\SchoolLife\Models\AbsenceJustification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AbsenceJustification
 */
final class AbsenceJustificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->getLabel(),
            'comment' => $this->comment,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
        ];
    }
}
