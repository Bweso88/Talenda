<?php

use App\Filament\School\Pages\CashDesk;
use App\Filament\School\Pages\FeeGrid;
use App\Filament\School\Pages\UnpaidReport;
use App\Filament\School\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\School\Resources\Invoices\RelationManagers\PaymentsRelationManager;
use App\Filament\School\Resources\PaymentPlans\Pages\CreatePaymentPlan;
use App\Models\User;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Finances\Actions\CashDesk as CashDeskAction;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Enums\PaymentStatus;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\CashSession;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\FeeType;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\Payment;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-12-15 10:00:00');

    [$this->school, $this->admin] = provisionSchool('lycee-a');
    [$this->otherSchool] = provisionSchool('lycee-b');

    $this->cashier = User::factory()->memberOf($this->school, 'cashier')->create();
    $this->accountant = User::factory()->memberOf($this->school, 'accountant')->create();

    app(TenantContext::class)->set($this->school);
    $this->year = SchoolYear::query()->open()->sole();
    $this->class = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    feeGrid('6E');
    $enrollment = app(EnrollNewStudent::class)->handle(newStudentData(), [], $this->class);
    $this->invoice = Invoice::firstWhere('enrollment_id', $enrollment->id);
});

afterEach(fn () => Carbon::setTestNow());

it('ouvre les écrans financiers pour l\'administrateur', function (string $path) {
    $this->actingAs($this->admin)->get("/ecole/lycee-a/{$path}")->assertOk();
})->with(['caisse', 'factures', 'impayes', 'grille-tarifaire', 'plans-de-paiement', 'plans-de-paiement/nouveau', 'types-de-frais', 'caisses', 'sessions-de-caisse']);

it('ouvre la fiche d\'une facture', function () {
    $this->actingAs($this->admin)->get("/ecole/lycee-a/factures/{$this->invoice->id}")->assertOk()->assertSee($this->invoice->number);
});

it('donne au caissier la caisse et les factures, pas le contrôle des sessions ni les paramètres', function () {
    $this->actingAs($this->cashier)->get('/ecole/lycee-a/caisse')->assertOk();
    $this->actingAs($this->cashier)->get('/ecole/lycee-a/factures')->assertOk();
    $this->actingAs($this->cashier)->get('/ecole/lycee-a/sessions-de-caisse')->assertForbidden();
    $this->actingAs($this->cashier)->get('/ecole/lycee-a/plans-de-paiement/nouveau')->assertForbidden();
});

it('ferme les finances aux enseignants', function () {
    $teacher = User::factory()->memberOf($this->school, 'teacher')->create();

    $this->actingAs($teacher)->get('/ecole/lycee-a/caisse')->assertForbidden();
    $this->actingAs($teacher)->get('/ecole/lycee-a/factures')->assertForbidden();
});

it('ouvre la caisse, encaisse et clôture depuis l\'écran de caisse', function () {
    actInSchool($this->cashier, $this->school);

    Livewire::test(CashDesk::class)
        ->callAction('openSession', ['cash_register_id' => CashRegister::sole()->id, 'opening_float' => 10000])
        ->assertNotified('Caisse ouverte.');

    Livewire::withQueryParams(['facture' => $this->invoice->uuid])->test(CashDesk::class)
        ->assertSchemaStateSet(['invoice_id' => $this->invoice->id, 'amount' => 25000], 'form')
        ->fillForm(['amount' => 45000, 'method' => 'cash', 'payer_name' => 'Mme Okemba'])
        ->call('recordPayment')
        ->assertNotified();

    expect(Payment::sole())
        ->amount->toBe(45000)
        ->receipt_number->toBe('R2026-00001');

    Livewire::test(CashDesk::class)
        ->callAction('closeSession', ['counted_cash' => 55000])
        ->assertNotified('Caisse clôturée.');

    expect(CashSession::sole()->difference())->toBe(0);
});

it('signale à la caisse un paiement mobile money sans référence', function () {
    actInSchool($this->cashier, $this->school);

    Livewire::test(CashDesk::class)
        ->fillForm(['invoice_id' => $this->invoice->id, 'amount' => 25000, 'method' => 'mobile_money', 'paid_on' => '2026-12-15'])
        ->call('recordPayment')
        ->assertHasFormErrors(['reference']);

    expect(Payment::count())->toBe(0);
});

it('sert le reçu en PDF, et pas celui d\'une autre école', function () {
    app(CashDeskAction::class)->open(CashRegister::sole(), 0, $this->cashier);
    $payment = app(RecordPayment::class)->handle($this->invoice, 25000, PaymentMethod::Cash, $this->cashier);

    $this->actingAs($this->cashier)
        ->get("/ecole/lycee-a/recus/{$payment->uuid}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $otherAdmin = User::factory()->memberOf($this->otherSchool, 'school_admin')->create();
    $this->actingAs($otherAdmin)->get("/ecole/lycee-b/recus/{$payment->uuid}")->assertNotFound();
});

it('accorde une réduction depuis la fiche facture', function () {
    actInSchool($this->admin, $this->school);

    Livewire::test(ViewInvoice::class, ['record' => $this->invoice->id])
        ->callAction('addDiscount', ['label' => 'Réduction fratrie', 'amount' => 18000, 'reason' => 'Troisième enfant'])
        ->assertNotified('Réduction accordée.');

    expect($this->invoice->fresh()->statement()->total)->toBe(187000);
});

it('fait annuler un encaissement par une autre personne que le caissier', function () {
    app(CashDeskAction::class)->open(CashRegister::sole(), 0, $this->cashier);
    $payment = app(RecordPayment::class)->handle($this->invoice, 25000, PaymentMethod::Cash, $this->cashier);

    actInSchool($this->accountant, $this->school);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $this->invoice, 'pageClass' => ViewInvoice::class])
        ->callAction(TestAction::make('reverse')->table($payment), ['reason' => 'Saisi deux fois']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Reversed);
});

it('enregistre la grille tarifaire', function () {
    actInSchool($this->admin, $this->school);
    $level = Level::firstWhere('code', '5E');
    $scolarite = FeeType::firstWhere('code', 'SCOLARITE');

    Livewire::test(FeeGrid::class)
        ->fillForm(["amounts.{$level->id}.{$scolarite->id}" => 195000])
        ->callAction('save')
        ->assertNotified('Grille tarifaire enregistrée.');

    expect(FeeAmount::where('level_id', $level->id)->value('amount'))->toBe(195000);
});

it('refuse un plan de paiement dont les parts ne font pas 100 %', function () {
    actInSchool($this->admin, $this->school);

    Livewire::test(CreatePaymentPlan::class)
        ->fillForm([
            'school_year_id' => $this->year->id,
            'name' => 'Semestriel',
            'installments' => [
                ['label' => '1er semestre', 'due_on' => '2026-10-10', 'percentage' => 50],
                ['label' => '2e semestre', 'due_on' => '2027-02-10', 'percentage' => 40],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['installments']);
});

it('liste les impayés et les exporte en Excel', function () {
    Carbon::setTestNow('2027-01-20 10:00:00');
    actInSchool($this->admin, $this->school);

    Livewire::test(UnpaidReport::class)
        ->assertCanSeeTableRecords([$this->invoice->id])
        ->callAction('export')
        ->assertFileDownloaded('impayes-2027-01-20.xlsx');
});
