<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Resources;

use App\Modules\SchoolLife\Support\AttendanceTotal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AttendanceTotal $resource
 */
final class AttendanceTotalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'absence_minutes' => $this->resource->absenceMinutes,
            'justified_absence_minutes' => $this->resource->justifiedAbsenceMinutes,
            'unjustified_absence_minutes' => $this->resource->unjustifiedAbsenceMinutes(),
            'late_count' => $this->resource->lateCount,
            'label' => $this->resource->shortLabel(),
        ];
    }
}
