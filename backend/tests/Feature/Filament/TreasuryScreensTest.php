<?php

use App\Filament\School\Pages\CashDesk;
use App\Filament\School\Resources\CashOperations\Pages\ManageCashOperations;
use App\Models\User;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Site;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Finances\Actions\CashDesk as CashDeskAction;
use App\Modules\Finances\Actions\CashOperations;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Enums\CashOperationStatus;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\CashOperation;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\ExpenseCategory;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Models\TreasurySetting;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-12-15 10:00:00');

    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);

    $this->cashier = User::factory()->memberOf($this->school, 'cashier')->create();
    $this->director = User::factory()->memberOf($this->school, 'director')->create();
    $this->register = CashRegister::sole();
    $this->year = SchoolYear::query()->open()->sole();
    $this->class = SchoolClass::create(['school_year_id' => $this->year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    feeGrid('6E');
    $this->invoice = Invoice::firstWhere('enrollment_id', app(EnrollNewStudent::class)->handle(newStudentData(), [], $this->class)->id);

    app(CashDeskAction::class)->open($this->register, 0, $this->cashier);
    app(RecordPayment::class)->handle($this->invoice, 100000, PaymentMethod::Cash, $this->cashier);
});

afterEach(fn () => Carbon::setTestNow());

it('ouvre les écrans de trésorerie à la direction', function (string $path) {
    $this->actingAs($this->director)->get("/ecole/lycee-a/{$path}")->assertOk();
})->with(['tresorerie', 'sites', 'categories-de-depense', 'caisses']);

it('ouvre la trésorerie au caissier, pas aux enseignants', function () {
    $this->actingAs($this->cashier)->get('/ecole/lycee-a/tresorerie')->assertOk()->assertSee('Caisse principale');

    $teacher = User::factory()->memberOf($this->school, 'teacher')->create();
    $this->actingAs($teacher)->get('/ecole/lycee-a/tresorerie')->assertForbidden();
});

it('fait saisir une dépense au caissier et valider au-delà du seuil par la direction', function () {
    actInSchool($this->cashier, $this->school);
    $water = ExpenseCategory::firstWhere('name', 'Eau');

    Livewire::test(ManageCashOperations::class)
        ->callAction('expense', ['cash_register_id' => $this->register->id, 'expense_category_id' => $water->id, 'amount' => 80000, 'description' => 'Citerne d\'eau', 'occurred_on' => '2026-12-15'])
        ->assertNotified('OP2026-00001 enregistrée : au-delà du seuil, elle attend la validation de la direction.');

    $operation = CashOperation::sole();
    expect($operation->status)->toBe(CashOperationStatus::PendingApproval);

    actInSchool($this->director, $this->school);

    Livewire::test(ManageCashOperations::class)
        ->assertSee('1 à valider')
        ->callAction(TestAction::make('approve')->table($operation))
        ->assertNotified('Opération validée.');

    expect($operation->fresh()->status)->toBe(CashOperationStatus::Done)
        ->and($this->register->balance())->toBe(20000);
});

it('justifie une avance depuis la liste des opérations', function () {
    $advance = app(CashOperations::class)->advance($this->register, ExpenseCategory::firstWhere('name', 'Fournitures de bureau'), 20000, 'Craies', $this->cashier, beneficiary: 'M. Ngoma');
    actInSchool($this->cashier, $this->school);

    Livewire::test(ManageCashOperations::class)
        ->callAction(TestAction::make('settle')->table($advance), ['spent' => 17500, 'returned' => 2500])
        ->assertNotified('Avance justifiée.');

    expect($this->register->balance())->toBe(82500);
});

it('règle le seuil et exporte le journal comptable pour le comptable', function () {
    actInSchool($this->director, $this->school);

    Livewire::test(ManageCashOperations::class)
        ->callAction(TestAction::make('threshold')->arguments([]), ['approval_threshold' => 100000])
        ->assertNotified('Seuil enregistré.')
        ->callAction('exportJournal', ['from' => '2026-12-01', 'to' => '2026-12-31'])
        ->assertFileDownloaded('journal-2026-12-01-2026-12-31.csv');

    expect(TreasurySetting::current()->approval_threshold)->toBe(100000);
});

it('ne montre au caissier d\'un site que les élèves de son site', function () {
    $north = Site::create(['name' => 'Site Nord']);
    $northClass = SchoolClass::create(['school_year_id' => $this->year->id, 'site_id' => $north->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e Nord']);
    app(EnrollNewStudent::class)->handle(newStudentData(['first_names' => 'Exaucé', 'gender' => 'M']), [], $northClass);
    $northRegister = CashRegister::create(['name' => 'Caisse Nord', 'site_id' => $north->id]);
    $northCashier = User::factory()->memberOf($this->school, 'cashier')->create();
    app(CashDeskAction::class)->open($northRegister, 0, $northCashier);

    actInSchool($northCashier, $this->school);

    $results = Livewire::test(CashDesk::class)->instance()->form->getComponent('invoice_id')->getSearchResults('Mabiala');

    expect(array_values($results))->toHaveCount(1)
        ->and(array_values($results)[0])->toContain('Exaucé');
});
