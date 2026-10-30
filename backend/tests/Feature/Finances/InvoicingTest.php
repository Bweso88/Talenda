<?php

use App\Modules\Academic\Actions\PrepareNextSchoolYear;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Actions\ReEnrollStudent;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Finances\Actions\ApplyFeeGrid;
use App\Modules\Finances\Actions\ManageInvoice;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\FeeType;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\InvoiceLine;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Models\PaymentPlan;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-20 10:00:00');

    [$this->tenant, $this->admin] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->year = SchoolYear::query()->open()->sole();
    $this->sixthA = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    feeGrid('6E');
});

afterEach(fn () => Carbon::setTestNow());

function enrollIn(SchoolClass $class, array $student = []): Invoice
{
    $enrollment = app(EnrollNewStudent::class)->handle(newStudentData($student), [], $class);

    return Invoice::query()->where('enrollment_id', $enrollment->id)->sole();
}

function pay(Invoice $invoice, int $amount, string $paidOn): void
{
    Payment::create([
        'invoice_id' => $invoice->id,
        'receipt_number' => uniqid('T'),
        'amount' => $amount,
        'method' => 'bank_transfer',
        'reference' => 'TEST',
        'paid_on' => $paidOn,
        'recorded_by' => test()->admin->id,
    ]);
    $invoice->unsetRelation('payments');
}

it('crée la facture de l\'inscription avec les frais obligatoires de la grille', function () {
    FeeAmount::create(['school_year_id' => $this->year->id, 'level_id' => $this->sixthA->level_id, 'fee_type_id' => FeeType::firstWhere('code', 'TENUE')->id, 'amount' => 15000]);

    $invoice = enrollIn($this->sixthA);

    expect($invoice->number)->toBe('F2026-00001')
        ->and($invoice->paymentPlan->name)->toBe('Mensuel')
        ->and($invoice->lines->pluck('amount', 'label')->all())->toBe(['Frais d\'inscription' => 25000, 'Scolarité' => 180000]);
});

it('facture les frais de réinscription aux élèves réinscrits seulement', function () {
    feeGrid('5E', ['INSCRIPTION' => 25000, 'REINSCRIPTION' => 10000, 'SCOLARITE' => 190000]);
    $first = enrollIn($this->sixthA);
    $fifthA = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', '5E')->id, 'name' => '5e A']);

    $first->enrollment->update(['status' => EnrollmentStatus::Withdrawn]);
    $reEnrollment = app(ReEnrollStudent::class)->handle($first->student, $fifthA);

    expect(Invoice::firstWhere('enrollment_id', $reEnrollment->id)->lines->pluck('label')->all())
        ->toBe(['Frais de réinscription', 'Scolarité']);
});

it('répartit la scolarité selon le plan mensuel, au franc près', function () {
    $statement = enrollIn($this->sixthA)->statement();

    $amounts = array_map(fn ($installment) => $installment->amount, $statement->installments);

    expect($statement->total)->toBe(205000)
        ->and(count($amounts))->toBe(10)
        ->and($statement->installments[0]->label)->toBe('À l\'inscription')
        ->and($amounts[0])->toBe(25000)
        ->and(array_sum($amounts))->toBe(205000)
        ->and($amounts[1])->toBe(20016)
        ->and($amounts[9])->toBe(19998);
});

it('ne met pas en retard dès son arrivée un élève inscrit en cours d\'année', function () {
    $statement = enrollIn($this->sixthA)->statement(Carbon::parse('2026-11-20'));

    expect($statement->installments[1]->label)->toBe('Octobre')
        ->and($statement->installments[1]->dueOn->toDateString())->toBe('2026-11-20')
        ->and($statement->overdue())->toBe(0)
        ->and($statement->installments[3]->dueOn->toDateString())->toBe('2026-12-10');
});

it('affecte les paiements aux échéances les plus anciennes et calcule les retards', function () {
    $invoice = enrollIn($this->sixthA);
    pay($invoice, 35000, '2026-11-02');

    $statement = $invoice->statement(Carbon::parse('2026-12-15'));

    // Inscription (25 000) réglée ; octobre (20 016) partiellement réglé
    // (10 000) ; octobre et novembre dus depuis l'inscription (20/11),
    // décembre échu le 10/12.
    expect($statement->installments[0]->isSettled())->toBeTrue()
        ->and($statement->installments[1]->paid)->toBe(10000)
        ->and($statement->installments[1]->statusLabel())->toBe('En retard (partiel)')
        ->and($statement->overdue())->toBe(10016 + 19998 + 19998)
        ->and($statement->balance())->toBe(170000)
        ->and($statement->nextDue()->label)->toBe('Octobre');
});

it('passe du mensuel au trimestriel sans perdre les paiements', function () {
    $invoice = enrollIn($this->sixthA);
    pay($invoice, 85000, '2026-10-05');

    app(ManageInvoice::class)->changePaymentPlan($invoice, PaymentPlan::firstWhere('name', 'Trimestriel'));

    $statement = $invoice->fresh()->statement(Carbon::parse('2026-12-01'));

    expect(count($statement->installments))->toBe(4)
        ->and($statement->installments[1]->amount)->toBe(60012)
        ->and($statement->installments[1]->paid)->toBe(60000)
        ->and($statement->overdue())->toBe(12)
        ->and($statement->paid)->toBe(85000);
});

it('répartit une réduction sur les échéances de scolarité', function () {
    $invoice = enrollIn($this->sixthA);

    app(ManageInvoice::class)->addDiscount($invoice, 18000, 'Réduction fratrie (10 %)', 'Deuxième enfant inscrit');

    $statement = $invoice->fresh()->statement();

    expect($statement->total)->toBe(187000)
        ->and($statement->installments[1]->amount)->toBe(18014);
});

it('refuse une réduction qui rendrait la facture inférieure au déjà payé', function () {
    $invoice = enrollIn($this->sixthA);
    pay($invoice, 200000, '2026-10-05');

    app(ManageInvoice::class)->addDiscount($invoice, 10000, 'Bourse', 'Mérite');
})->throws(BusinessRuleViolation::class, 'déjà payés');

it('remet le reste dû au départ d\'un élève, sans toucher aux sommes payées', function () {
    $invoice = enrollIn($this->sixthA);
    pay($invoice, 65000, '2026-10-05');

    app(ManageInvoice::class)->cancelRemainingBalance($invoice, 'Départ le 20/11');

    $statement = $invoice->fresh()->statement();

    expect($statement->total)->toBe(65000)
        ->and($statement->balance())->toBe(0)
        ->and($statement->overdue())->toBe(0);
});

it('ajoute un frais facultatif au montant de la grille', function () {
    FeeAmount::create(['school_year_id' => $this->year->id, 'level_id' => $this->sixthA->level_id, 'fee_type_id' => FeeType::firstWhere('code', 'TENUE')->id, 'amount' => 15000]);
    $invoice = enrollIn($this->sixthA);

    app(ManageInvoice::class)->addFee($invoice, FeeType::firstWhere('code', 'TENUE'));

    expect($invoice->fresh()->statement()->installments[0]->amount)->toBe(40000);
});

it('complète les factures quand la grille est saisie après les inscriptions', function () {
    FeeAmount::query()->delete();
    $invoice = enrollIn($this->sixthA);
    feeGrid('6E');

    expect(app(ApplyFeeGrid::class)->handle($invoice))->toBe(2)
        ->and(app(ApplyFeeGrid::class)->handle($invoice->fresh()))->toBe(0)
        ->and($invoice->fresh()->lines)->toHaveCount(2);
});

it('interdit de modifier ou supprimer une ligne de facture', function () {
    $line = enrollIn($this->sixthA)->lines->first();

    expect(fn () => $line->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $line->delete())->toThrow(LogicException::class);
});

it('reprend la grille et les plans ajustés pour l\'année suivante', function () {
    PaymentPlan::firstWhere('name', 'Annuel')->delete();

    $next = app(PrepareNextSchoolYear::class)->handle($this->year);

    expect(FeeAmount::where('school_year_id', $next->id)->count())->toBe(2)
        ->and(PaymentPlan::where('school_year_id', $next->id)->orderBy('name')->pluck('name')->all())->toBe(['Mensuel', 'Trimestriel'])
        ->and(PaymentPlan::where('school_year_id', $next->id)->firstWhere('name', 'Mensuel')->installments->first()->due_on->toDateString())->toBe('2027-10-10');
});

it('crée une ligne de facture sans auteur pour la facturation automatique', function () {
    expect(InvoiceLine::query()->whereNotNull('created_by')->count())->toBe(0);
    enrollIn($this->sixthA);
    expect(InvoiceLine::query()->whereNull('created_by')->count())->toBe(2);
});
