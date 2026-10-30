<?php

use App\Models\User;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Site;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Finances\Actions\CashDesk;
use App\Modules\Finances\Actions\CashOperations;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Actions\ReversePayment;
use App\Modules\Finances\Enums\CashOperationStatus;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\ExpenseCategory;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\JournalLine;
use App\Modules\Finances\Models\TreasurySetting;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-12-15 10:00:00');

    [$this->tenant] = provisionSchool();
    app(TenantContext::class)->set($this->tenant);

    $this->cashier = User::factory()->memberOf($this->tenant, 'cashier')->create();
    $this->director = User::factory()->memberOf($this->tenant, 'director')->create();
    $this->accountant = User::factory()->memberOf($this->tenant, 'accountant')->create();
    $this->register = CashRegister::sole();
    $this->water = ExpenseCategory::firstWhere('name', 'Eau');
    $this->supplies = ExpenseCategory::firstWhere('name', 'Fournitures de bureau');
    $this->teacher = StaffMember::factory()->create(['first_name' => 'Jean', 'last_name' => 'Okemba']);

    $year = SchoolYear::query()->open()->sole();
    $class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    feeGrid('6E');
    $this->invoice = Invoice::firstWhere('enrollment_id', app(EnrollNewStudent::class)->handle(newStudentData(), [], $class)->id);

    // Caisse ouverte avec 10 000 F, puis 100 000 F encaissés.
    $this->session = app(CashDesk::class)->open($this->register, 10000, $this->cashier);
    app(RecordPayment::class)->handle($this->invoice, 100000, PaymentMethod::Cash, $this->cashier);
});

afterEach(fn () => Carbon::setTestNow());

function treasury(): CashOperations
{
    return app(CashOperations::class);
}

/**
 * @return list<array{0: string, 1: int, 2: int}> compte, débit, crédit
 */
function journalOf(string $reference): array
{
    return JournalLine::query()
        ->whereHas('journalEntry', fn ($query) => $query->where('reference', $reference))
        ->orderBy('id')
        ->get()
        ->map(fn (JournalLine $line): array => [$line->account_code, $line->debit, $line->credit])
        ->all();
}

it('rattache les classes et la caisse au site principal de l\'école', function () {
    expect(Site::sole()->name)->toBe('Site principal')
        ->and(SchoolClass::sole()->site_id)->toBe(Site::sole()->id)
        ->and($this->register->site_id)->toBe(Site::sole()->id)
        ->and(ExpenseCategory::count())->toBe(11);
});

it('passe chaque encaissement au journal et suit le solde de la caisse', function () {
    expect(journalOf('R2026-00001'))->toBe([['5711', 100000, 0], ['411', 0, 100000]])
        ->and($this->register->balance())->toBe(110000);

    app(RecordPayment::class)->handle($this->invoice, 20000, PaymentMethod::MobileMoney, $this->cashier, 'MP123');
    expect(journalOf('R2026-00002'))->toBe([['5521', 20000, 0], ['411', 0, 20000]]);

    $payment = $this->invoice->payments()->firstWhere('receipt_number', 'R2026-00001');
    app(ReversePayment::class)->handle($payment, 'Saisi deux fois', $this->accountant);

    expect($this->register->balance())->toBe(10000)
        ->and(journalOf('R2026-00001'))->toHaveCount(4);
});

it('paie une dépense sous le seuil directement, au-dessus après validation de la direction', function () {
    $small = treasury()->expense($this->register, $this->water, 15000, 'Facture LCDE novembre', $this->cashier, 'LCDE-2026-11');

    expect($small->status)->toBe(CashOperationStatus::Done)
        ->and(journalOf($small->number))->toBe([['6051', 15000, 0], ['5711', 0, 15000]])
        ->and($this->register->balance())->toBe(95000);

    $big = treasury()->expense($this->register, $this->supplies, 60000, 'Ramettes de papier', $this->cashier);

    expect($big->status)->toBe(CashOperationStatus::PendingApproval)
        ->and($this->register->balance())->toBe(95000);

    expect(fn () => treasury()->approve($big, $this->cashier))->toThrow(BusinessRuleViolation::class, 'Seule la direction');

    treasury()->approve($big, $this->director);

    expect($big->fresh()->status)->toBe(CashOperationStatus::Done)
        ->and($big->fresh()->approved_by)->toBe($this->director->id)
        ->and($this->register->balance())->toBe(35000);
});

it('refuse une demande sans toucher à la caisse', function () {
    TreasurySetting::current()->update(['approval_threshold' => 10000]);
    $request = treasury()->expense($this->register, $this->water, 20000, 'Citerne', $this->cashier);

    treasury()->reject($request, 'Pas prévu au budget', $this->director);

    expect($request->fresh()->status)->toBe(CashOperationStatus::Rejected)
        ->and($this->register->balance())->toBe(110000);
});

it('ne laisse pas une caisse passer sous zéro', function () {
    treasury()->expense($this->register, $this->water, 120000, 'Gros travaux', $this->director);
})->throws(BusinessRuleViolation::class, 'Solde insuffisant');

it('ne laisse un caissier sortir de l\'argent que de la caisse qu\'il tient', function () {
    $other = User::factory()->memberOf($this->tenant, 'cashier')->create();

    treasury()->expense($this->register, $this->water, 1000, 'Eau', $other);
})->throws(BusinessRuleViolation::class, 'Vous ne tenez pas');

it('paie un salaire en charge de personnel et refuse un doublon sans motif', function () {
    $salary = treasury()->salary($this->register, $this->teacher, '2026-11', 45000, $this->cashier);

    expect(journalOf($salary->number))->toBe([['661', 45000, 0], ['422', 0, 45000], ['422', 45000, 0], ['5711', 0, 45000]]);

    expect(fn () => treasury()->salary($this->register, $this->teacher, '2026-11', 5000, $this->cashier))
        ->toThrow(BusinessRuleViolation::class, 'déjà enregistré');

    expect(treasury()->salary($this->register, $this->teacher, '2026-11', 5000, $this->cashier, 'Rappel heures supplémentaires')->status)
        ->toBe(CashOperationStatus::Done);
});

it('suit une avance jusqu\'à sa justification, avec la monnaie rendue', function () {
    $advance = treasury()->advance($this->register, $this->supplies, 20000, 'Craies et marqueurs', $this->cashier, $this->teacher);

    expect($advance->status)->toBe(CashOperationStatus::Outstanding)
        ->and($this->register->balance())->toBe(90000);

    expect(fn () => treasury()->settleAdvance($advance, 17500, 1000, $this->cashier))
        ->toThrow(BusinessRuleViolation::class, 'doivent faire l\'avance');

    treasury()->settleAdvance($advance, 17500, 2500, $this->cashier);

    expect($advance->fresh()->status)->toBe(CashOperationStatus::Settled)
        ->and($this->register->balance())->toBe(92500)
        ->and(journalOf($advance->number))->toBe([
            ['421', 20000, 0], ['5711', 0, 20000],
            ['6055', 17500, 0], ['5711', 2500, 0], ['421', 0, 20000],
        ]);
});

it('rembourse un achat plus cher que l\'avance, ou reprend tout si l\'achat est annulé', function () {
    $dearer = treasury()->advance($this->register, $this->supplies, 10000, 'Cartouche d\'encre', $this->cashier, beneficiary: 'M. Ngoma (chauffeur)');

    expect(fn () => treasury()->settleAdvance($dearer, 12000, 0, $this->cashier))->toThrow(BusinessRuleViolation::class, 'expliquez');

    treasury()->settleAdvance($dearer, 12000, 0, $this->cashier, 'Prix augmenté');
    expect($this->register->balance())->toBe(98000);

    $cancelled = treasury()->advance($this->register, $this->supplies, 5000, 'Agrafeuse', $this->cashier, $this->teacher);
    treasury()->settleAdvance($cancelled, 0, 5000, $this->cashier, 'Achat annulé');

    expect($this->register->balance())->toBe(98000);
});

it('vire l\'argent d\'un site à la caisse centrale, avec comptage à la réception', function () {
    $central = CashRegister::create(['name' => 'Caisse centrale']);
    $transfer = treasury()->transfer($this->register, $central, 100000, $this->cashier);

    expect($transfer->status)->toBe(CashOperationStatus::InTransit)
        ->and($this->register->balance())->toBe(10000)
        ->and($central->balance())->toBe(0);

    expect(fn () => treasury()->receiveTransfer($transfer, 99000, $this->accountant))->toThrow(BusinessRuleViolation::class, 'Écart');

    treasury()->receiveTransfer($transfer, 99000, $this->accountant, 'Billet de 1 000 manquant');

    expect($central->balance())->toBe(99000)
        ->and(journalOf($transfer->number))->toBe([
            ['585', 100000, 0], ['5711', 0, 100000],
            ['5711', 99000, 0], ['585', 0, 100000], ['658', 1000, 0],
        ]);
});

it('annule un virement encore en route : l\'argent revient', function () {
    $bank = CashRegister::create(['name' => 'BGFI', 'kind' => 'bank']);
    $transfer = treasury()->transfer($this->register, $bank, 50000, $this->cashier);

    expect($bank->account_code)->toBe('5211');

    treasury()->reverse($transfer, 'Banque fermée', $this->cashier);

    expect($transfer->fresh()->status)->toBe(CashOperationStatus::Cancelled)
        ->and($this->register->balance())->toBe(110000);
});

it('fait annuler une dépense par la direction, jamais par qui l\'a saisie', function () {
    $expense = treasury()->expense($this->register, $this->water, 15000, 'Facture en double', $this->cashier);

    expect(fn () => treasury()->reverse($expense, 'Erreur', $this->cashier))->toThrow(BusinessRuleViolation::class, 'Seule la direction');

    treasury()->reverse($expense, 'Facture déjà payée en octobre', $this->director);

    expect($expense->fresh()->status)->toBe(CashOperationStatus::Reversed)
        ->and($this->register->balance())->toBe(110000);
});

it('compte la caisse à la clôture et passe l\'écart au journal, puis contrôle le fond à la réouverture', function () {
    treasury()->expense($this->register, $this->water, 15000, 'Eau', $this->cashier);

    expect($this->session->fresh()->currentExpectedCash())->toBe(95000);

    app(CashDesk::class)->close($this->session, 94500, 'Pièce de 500 introuvable', $this->cashier);
    expect($this->register->balance())->toBe(94500);

    expect(fn () => app(CashDesk::class)->open($this->register, 90000, $this->cashier))->toThrow(BusinessRuleViolation::class, 'devrait contenir');

    app(CashDesk::class)->open($this->register, 94500, $this->cashier);

    expect((int) JournalLine::where('account_code', '658')->sum('debit'))->toBe(500);
});

it('garde un journal toujours équilibré', function () {
    $central = CashRegister::create(['name' => 'Caisse centrale']);
    treasury()->expense($this->register, $this->water, 15000, 'Eau', $this->cashier);
    treasury()->salary($this->register, $this->teacher, '2026-11', 30000, $this->cashier);
    $advance = treasury()->advance($this->register, $this->supplies, 10000, 'Craies', $this->cashier, $this->teacher);
    treasury()->settleAdvance($advance, 8000, 2000, $this->cashier);
    treasury()->receiveTransfer(treasury()->transfer($this->register, $central, 20000, $this->cashier), 20000, $this->accountant);

    expect((int) JournalLine::sum('debit'))->toBe((int) JournalLine::sum('credit'))
        ->and($this->register->balance() + $central->balance())->toBe(110000 - 15000 - 30000 - 8000);
});
