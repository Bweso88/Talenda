<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Resources;

use App\Modules\Assessments\Models\TermResult;
use App\Modules\Assessments\Models\TermSubjectResult;
use App\Modules\SchoolLife\Support\AttendanceTotal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Résultats publiés d'un trimestre : exactement ce qui est imprimé sur le
 * bulletin (résultats figés). Les matières ne sont incluses que si elles
 * ont été chargées (détail).
 *
 * @mixin TermResult
 */
final class TermResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->summary(),
            'class_name' => $this->class_name,
            'class_average' => self::number($this->class_average),
            'class_highest' => self::number($this->class_highest),
            'class_lowest' => self::number($this->class_lowest),
            'total_points' => self::number($this->total_points),
            'total_coefficient' => (float) $this->total_coefficient,
            'council_comment' => $this->council_comment,
            'head_teacher_name' => $this->head_teacher_name,
            'attendance' => AttendanceTotalResource::make(new AttendanceTotal($this->absence_minutes, $this->justified_absence_minutes, $this->late_count)),
            'subjects' => $this->whenLoaded('subjectResults', fn () => $this->subjectResults->map(fn (TermSubjectResult $subject): array => [
                'subject' => $subject->subject_name,
                'teacher' => $subject->teacher_name,
                'coefficient' => (float) $subject->coefficient,
                'average' => self::number($subject->average),
                'rank' => $subject->rank,
                'class_average' => self::number($subject->class_average),
                'class_highest' => self::number($subject->class_highest),
                'class_lowest' => self::number($subject->class_lowest),
                'comment' => $subject->comment,
            ])->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $this->resource->loadMissing('term.schoolYear');

        return [
            'uuid' => $this->uuid,
            'term' => $this->term->name,
            'school_year' => $this->term->schoolYear->name,
            'average' => self::number($this->average),
            'rank' => $this->rank,
            'rank_label' => $this->rankLabel(),
            'class_size' => $this->class_size,
            'distinction' => $this->distinction,
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }

    private static function number(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
