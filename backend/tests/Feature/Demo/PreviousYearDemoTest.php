<?php

use App\Filament\School\Pages\ClassCouncil;
use App\Models\User;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Models\AnnualResult;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\YearEndDecision;
use App\Modules\Finances\Models\Invoice;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PreviousYearDemoSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->school = Tenant::firstWhere('slug', 'lycee-demo-brazzaville');
    app(TenantContext::class)->set($this->school);
    $this->past = SchoolYear::firstWhere('name', '2025-2026');
    $this->class = SchoolClass::where('school_year_id', $this->past->id)->sole();
});

it('rejoue une année 2025-2026 complète et clôturée, sans toucher à l\'année en cours', function () {
    expect($this->past->status)->toBe(SchoolYearStatus::Closed)
        ->and(SchoolYear::query()->open()->sole()->name)->toBe('2026-2027')
        ->and($this->class->enrollments()->count())->toBe(10)
        ->and($this->past->terms()->pluck('status')->unique()->all())->toBe([TermStatus::Published])
        ->and(TermResult::whereHas('term', fn ($query) => $query->where('school_year_id', $this->past->id))->count())->toBe(30)
        ->and(AnnualResult::whereIn('enrollment_id', $this->class->enrollments()->pluck('id'))->whereNotNull('rank')->count())->toBe(10)
        ->and($this->class->enrollments()->pluck('decision')->filter()->count())->toBe(10)
        ->and($this->class->enrollments()->pluck('decision')->contains(YearEndDecision::Repeat))->toBeTrue();

    // Deux familles gardent un reste à payer.
    $balances = Invoice::where('school_year_id', $this->past->id)->get()->map(fn (Invoice $invoice): int => $invoice->statement()->balance());
    expect($balances->filter()->sort()->values()->all())->toBe([40000, 85000]);

    // Une seconde exécution ne fait rien.
    $this->seed(PreviousYearDemoSeeder::class);
    expect(SchoolYear::where('name', '2025-2026')->count())->toBe(1);
});

it('imprime bulletins, relevés de notes et procès-verbal d\'une année clôturée, sans pouvoir la modifier', function () {
    actInSchool(User::firstWhere('phone', '+242061111111'), $this->school);
    $lastTerm = Term::where('school_year_id', $this->past->id)->orderByDesc('position')->first();

    Livewire::test(ClassCouncil::class)
        ->fillForm(['school_year_id' => $this->past->id])
        ->assertSchemaStateSet(['term_id' => $lastTerm->id, 'school_class_id' => $this->class->id])
        ->assertActionHidden('reopen')
        ->assertActionHidden('lock')
        ->callAction('reportCards')->assertFileDownloaded('bulletins-6e-a-troisieme-trimestre-2025-2026.pdf')
        ->callAction('transcripts')->assertFileDownloaded('releves-de-notes-6e-a-troisieme-trimestre-2025-2026.pdf')
        ->callAction('councilMinutes')->assertFileDownloaded('proces-verbal-6e-a-troisieme-trimestre-2025-2026.pdf');
});
