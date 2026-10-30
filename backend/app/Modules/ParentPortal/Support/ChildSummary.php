<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Support;

use App\Modules\Assessments\Models\TermResult;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Student;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Support\InvoiceStatement;
use App\Modules\SchoolLife\Support\AttendanceTotal;
use App\Modules\SchoolLife\Support\AttendanceTotals;

/**
 * Ce que le parent voit d'un coup d'œil sur l'accueil de l'app, pour
 * l'inscription la plus récente de l'enfant.
 */
final readonly class ChildSummary
{
    private function __construct(
        public ?Enrollment $enrollment,
        public ?InvoiceStatement $statement,
        public ?TermResult $lastResult,
        public AttendanceTotal $attendance,
    ) {}

    public static function of(Student $student): self
    {
        $enrollment = self::latestEnrollment($student);

        if ($enrollment === null) {
            return new self(null, null, null, new AttendanceTotal);
        }

        $invoice = Invoice::query()
            ->where('enrollment_id', $enrollment->id)
            ->with(['lines', 'payments', 'paymentPlan.installments', 'enrollment'])
            ->first();

        return new self(
            enrollment: $enrollment,
            statement: $invoice?->statement(),
            lastResult: TermResult::query()->where('enrollment_id', $enrollment->id)->latest('published_at')->first(),
            attendance: AttendanceTotals::between([$enrollment->id], $enrollment->schoolYear->starts_on, $enrollment->schoolYear->ends_on)[$enrollment->id] ?? new AttendanceTotal,
        );
    }

    public static function latestEnrollment(Student $student): ?Enrollment
    {
        return $student->enrollments()
            ->with(['schoolYear', 'schoolClass'])
            ->get()
            ->sortByDesc(fn (Enrollment $enrollment): string => $enrollment->schoolYear->starts_on->toDateString().' '.$enrollment->enrolled_on->toDateString())
            ->first();
    }
}
