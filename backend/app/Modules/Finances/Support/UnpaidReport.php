<?php

declare(strict_types=1);

namespace App\Modules\Finances\Support;

use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Enrollments\Models\StudentGuardian;
use App\Modules\Finances\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * État des impayés d'une année : élèves dont une échéance passée n'est pas
 * réglée, avec le contact du responsable qui paie (pour les relances).
 */
final class UnpaidReport
{
    /**
     * @return Collection<int, UnpaidRow>
     */
    public function rows(SchoolYear $schoolYear, ?int $schoolClassId = null, ?Carbon $today = null): Collection
    {
        return Invoice::query()
            ->where('school_year_id', $schoolYear->id)
            ->when($schoolClassId, fn ($query, int $classId) => $query->whereHas('enrollment', fn ($enrollments) => $enrollments->where('school_class_id', $classId)))
            ->with(['lines', 'payments', 'paymentPlan.installments', 'enrollment.schoolClass', 'student.guardianLinks.guardian'])
            ->get()
            ->map(function (Invoice $invoice) use ($today): UnpaidRow {
                $statement = InvoiceStatement::for($invoice, $today);
                $payer = $this->payer($invoice);

                return new UnpaidRow(
                    invoice: $invoice,
                    student: $invoice->student->full_name,
                    registrationNumber: $invoice->student->registration_number,
                    class: $invoice->enrollment->schoolClass->name,
                    contact: $payer?->guardian->full_name,
                    phone: $payer?->guardian->phone,
                    total: $statement->total,
                    paid: $statement->paid,
                    balance: $statement->balance(),
                    overdue: $statement->overdue(),
                );
            })
            ->filter(fn (UnpaidRow $row): bool => $row->overdue > 0)
            ->sortBy([fn (UnpaidRow $a, UnpaidRow $b): int => [$a->class, $a->student] <=> [$b->class, $b->student]])
            ->values();
    }

    /**
     * Responsable à relancer : celui qui paie la scolarité, sinon le contact
     * principal, sinon le premier connu.
     */
    private function payer(Invoice $invoice): ?StudentGuardian
    {
        $links = $invoice->student->guardianLinks;

        return $links->firstWhere('is_financially_responsible', true)
            ?? $links->firstWhere('is_primary_contact', true)
            ?? $links->first();
    }
}
