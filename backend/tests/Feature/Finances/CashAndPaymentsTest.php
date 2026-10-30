<?php

use App\Models\User;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Finances\Actions\CashDesk;
use App\Modules\Finances\Actions\IssueReceipt;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Actions\ReversePayment;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Enums\PaymentStatus;
use App\Modules\Finances\Events\PaymentRecorded;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\InvoiceStatement;
use App\Modules\Finances\Support\UnpaidReport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Carbon::setTestNow('2026-12-15 10:00:00');

    [$this->tenant] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->cashier = User::factory()->memberOf($this->tenant, 'cashier')->create();
    $this->accountant = User::factory()->memberOf($this->tenant, 'accountant')->create();
    $this->register = CashRegister::sole();

    $year = SchoolYear::query()->open()->sole();
    $this->class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    feeGrid('6E');

    $enrollment = app(EnrollNewStudent::class)->handle(newStudentData(), [], $this->class);
    $this->invoice = Invoice::firstWhere('enrollment_id', $enrollment->id);
});

afterEach(fn () => Carbon::setTestNow());

function cashIn(int $amount, PaymentMethod $method = PaymentMethod::Cash, ?string $reference = null)
{
    return app(RecordPayment::class)->handle(test()->invoice, $amount, $method, test()->cashier, $reference, 'Mme Okemba');
}

it('exige une caisse ouverte pour encaisser des espèces', function () {
    cashIn(25000);
})->throws(BusinessRuleViolation::class, 'Ouvrez votre caisse');

it('encaisse, numérote les reçus sans trou et journalise', function () {
    Event::fake([PaymentRecorded::class]);
    app(CashDesk::class)->open($this->register, 10000, $this->cashier);

    $first = cashIn(25000);
    $second = cashIn(20000);

    expect($first->receipt_number)->toBe('R2026-00001')
        ->and($second->receipt_number)->toBe('R2026-00002')
        ->and($first->cash_session_id)->not->toBeNull()
        ->and($this->invoice->fresh()->statement()->paid)->toBe(45000)
        ->and(AuditLog::where('action', 'payment.recorded')->count())->toBe(2);

    Event::assertDispatchedTimes(PaymentRecorded::class, 2);
});

it('exige la référence d\'un paiement mobile money', function () {
    cashIn(25000, PaymentMethod::MobileMoney);
})->throws(BusinessRuleViolation::class, 'référence');

it('accepte le mobile money sans caisse ouverte, avec sa référence', function () {
    $payment = cashIn(25000, PaymentMethod::MobileMoney, 'MP261215.1043.A12345');

    expect($payment->cash_session_id)->toBeNull()
        ->and($payment->method)->toBe(PaymentMethod::MobileMoney);
});

it('refuse un montant supérieur au reste à payer', function () {
    app(CashDesk::class)->open($this->register, 0, $this->cashier);

    cashIn(205001);
})->throws(BusinessRuleViolation::class, 'dépasse le reste à payer');

it('garantit en base qu\'une référence accompagne tout paiement autre qu\'en espèces', function () {
    Payment::create([
        'invoice_id' => $this->invoice->id,
        'receipt_number' => 'X1',
        'amount' => 1000,
        'method' => 'cheque',
        'paid_on' => now(),
        'recorded_by' => $this->cashier->id,
    ]);
})->throws(QueryException::class);

describe('annulation', function () {
    beforeEach(function () {
        app(CashDesk::class)->open($this->register, 0, $this->cashier);
        $this->payment = cashIn(25000);
    });

    it('interdit au caissier d\'annuler son propre encaissement', function () {
        app(ReversePayment::class)->handle($this->payment, 'Erreur', $this->cashier);
    })->throws(BusinessRuleViolation::class, 'vous-même');

    it('annule un encaissement, le garde dans l\'historique et rétablit le solde', function () {
        app(ReversePayment::class)->handle($this->payment, 'Montant saisi deux fois', $this->accountant);

        expect($this->payment->fresh())
            ->status->toBe(PaymentStatus::Reversed)
            ->reversed_by->toBe($this->accountant->id)
            ->and($this->invoice->fresh()->statement()->paid)->toBe(0)
            ->and($this->accountant->can('reverse', $this->payment->fresh()))->toBeFalse();
    });

    it('interdit de modifier ou supprimer un paiement', function () {
        expect(fn () => $this->payment->update(['amount' => 1]))->toThrow(LogicException::class)
            ->and(fn () => $this->payment->delete())->toThrow(LogicException::class);
    });
});

describe('caisse', function () {
    it('n\'ouvre qu\'une session à la fois par caisse et par personne', function () {
        app(CashDesk::class)->open($this->register, 0, $this->cashier);

        expect(fn () => app(CashDesk::class)->open($this->register, 0, $this->accountant))
            ->toThrow(BusinessRuleViolation::class, 'déjà ouverte')
            ->and(fn () => app(CashDesk::class)->open(CashRegister::create(['name' => 'Caisse 2']), 0, $this->cashier))
            ->toThrow(BusinessRuleViolation::class, 'déjà une session ouverte');
    });

    it('clôture en comparant les espèces comptées aux espèces attendues', function () {
        $session = app(CashDesk::class)->open($this->register, 10000, $this->cashier);
        cashIn(25000);
        cashIn(15000, PaymentMethod::MobileMoney, 'MP123');
        $reversed = cashIn(5000);
        app(ReversePayment::class)->handle($reversed, 'Erreur', $this->accountant);

        app(CashDesk::class)->close($session, 35000, null, $this->cashier);

        expect($session->fresh())
            ->expected_cash->toBe(35000)
            ->counted_cash->toBe(35000)
            ->and($session->fresh()->difference())->toBe(0);
    });

    it('exige une explication en cas d\'écart', function () {
        $session = app(CashDesk::class)->open($this->register, 10000, $this->cashier);
        cashIn(25000);

        app(CashDesk::class)->close($session, 34000, '', $this->cashier);
    })->throws(BusinessRuleViolation::class, 'Écart de');
});

it('liste les impayés avec le contact du responsable qui paie', function () {
    $enrollment = app(EnrollNewStudent::class)->handle(newStudentData(['first_names' => 'Arsène', 'gender' => 'M']), [
        ['relationship' => 'mother', 'last_name' => 'Okemba', 'phone' => '06 555 00 01', 'is_primary_contact' => true],
        ['relationship' => 'father', 'last_name' => 'Mabiala', 'phone' => '05 555 00 02', 'is_financially_responsible' => true],
    ], $this->class);
    app(CashDesk::class)->open($this->register, 0, $this->cashier);
    cashIn(205000);

    // Un mois plus tard : octobre, novembre et décembre (dus depuis
    // l'inscription du 15/12) et janvier (10/01) sont échus.
    $rows = app(UnpaidReport::class)->rows(SchoolYear::query()->open()->sole(), today: Carbon::parse('2027-01-15'));

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->invoice->enrollment_id)->toBe($enrollment->id)
        ->and($rows->first()->overdue)->toBe(25000 + 20016 + 3 * 19998)
        ->and($rows->first()->contact)->toBe('MABIALA')
        ->and($rows->first()->phone)->toBe('+242055550002');
});

it('produit le reçu en PDF', function () {
    app(CashDesk::class)->open($this->register, 0, $this->cashier);

    expect(app(IssueReceipt::class)->handle(cashIn(25000)))->toStartWith('%PDF');
});

it('imprime sur le reçu la situation au moment du paiement, même réimprimé plus tard', function () {
    app(CashDesk::class)->open($this->register, 0, $this->cashier);
    $first = cashIn(25000);
    cashIn(40000);

    $statement = InvoiceStatement::for($this->invoice->fresh(), upTo: $first);

    expect($statement->paid)->toBe(25000)
        ->and($this->invoice->fresh()->statement()->paid)->toBe(65000);
});
