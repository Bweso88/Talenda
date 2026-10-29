<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Student;
use App\Modules\Finances\Enums\PaymentStatus;
use App\Modules\Finances\Policies\InvoicePolicy;
use App\Modules\Finances\Support\InvoiceStatement;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Facture de scolarité d'une inscription. Son solde et son échéancier
 * se lisent avec statement().
 *
 * @property int $id
 * @property string $uuid
 * @property int $enrollment_id
 * @property int $student_id
 * @property int $school_year_id
 * @property int|null $payment_plan_id
 * @property string $number
 * @property Enrollment $enrollment
 * @property Student $student
 * @property SchoolYear $schoolYear
 * @property PaymentPlan|null $paymentPlan
 * @property Collection<int, InvoiceLine> $lines
 * @property Collection<int, Payment> $payments
 */
#[UsePolicy(InvoicePolicy::class)]
#[Fillable(['enrollment_id', 'student_id', 'school_year_id', 'payment_plan_id', 'number'])]
class Invoice extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * @return BelongsTo<PaymentPlan, $this>
     */
    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('id');
    }

    /**
     * Tous les paiements, y compris annulés (historique).
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function validPayments(): HasMany
    {
        return $this->payments()->where('status', PaymentStatus::Valid);
    }

    /**
     * Total, payé, solde et échéancier à la date donnée.
     */
    public function statement(?Carbon $today = null): InvoiceStatement
    {
        $this->loadMissing(['lines', 'payments', 'paymentPlan.installments', 'enrollment']);

        return InvoiceStatement::for($this, $today);
    }
}
