<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Resources;

use App\Modules\Enrollments\Models\Student;
use App\Modules\ParentPortal\Support\ChildSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Enfant vu par son parent : identité, inscription en cours et résumé
 * (solde, dernière moyenne publiée, absences de l'année).
 *
 * @mixin Student
 */
final class ChildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = ChildSummary::of($this->resource);
        $enrollment = $summary->enrollment;

        return [
            'uuid' => $this->uuid,
            'last_name' => $this->last_name,
            'first_names' => $this->first_names,
            'full_name' => $this->full_name,
            'registration_number' => $this->registration_number,
            'gender' => $this->gender->value,
            'birth_date' => $this->birth_date->toDateString(),
            'enrollment' => $enrollment === null ? null : [
                'uuid' => $enrollment->uuid,
                'school_year' => $enrollment->schoolYear->name,
                'class_name' => $enrollment->schoolClass->name,
                'status' => $enrollment->status->value,
                'status_label' => $enrollment->status->getLabel(),
            ],
            'finance' => $summary->statement === null ? null : [
                'total' => $summary->statement->total,
                'paid' => $summary->statement->paid,
                'balance' => max(0, $summary->statement->balance()),
                'overdue' => $summary->statement->overdue(),
                'next_due' => ($next = $summary->statement->nextDue()) === null ? null : [
                    'label' => $next->label,
                    'due_on' => $next->dueOn->toDateString(),
                    'remaining' => $next->remaining(),
                ],
            ],
            'last_result' => $summary->lastResult === null ? null : TermResultResource::make($summary->lastResult)->summary(),
            'attendance' => AttendanceTotalResource::make($summary->attendance),
        ];
    }
}
