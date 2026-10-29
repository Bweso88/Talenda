<?php

use App\Modules\Academic\Actions\ApplyAcademicPreset;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\Cycle;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Stream;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2027-03-15 10:00:00'));

afterEach(fn () => Carbon::setTestNow());

it('crée les niveaux des cycles choisis, chaînés pour les passages', function () {
    [$tenant] = provisionSchool(cycles: ['PRIMAIRE', 'COLLEGE']);

    app(TenantContext::class)->run($tenant, function () {
        $levels = Level::query()->with('nextLevel')->orderBy('position')->get();

        expect($levels->pluck('code')->all())->toBe(['CP1', 'CP2', 'CE1', 'CE2', 'CM1', 'CM2', '6E', '5E', '4E', '3E'])
            ->and($levels->firstWhere('code', 'CM2')->nextLevel->code)->toBe('6E')
            ->and($levels->firstWhere('code', '3E')->next_level_id)->toBeNull()
            ->and($levels->firstWhere('code', 'CM2')->exam_name)->toBe('CEPE')
            ->and($levels->firstWhere('code', '3E')->exam_name)->toBe('BEPC');
    });
});

it('ne crée les séries que pour un lycée et filtre les matières par cycle', function () {
    [$college] = provisionSchool('college', ['COLLEGE']);
    [$lycee] = provisionSchool('lycee', ['LYCEE']);

    $context = app(TenantContext::class);

    expect($context->run($college, fn () => Stream::count()))->toBe(0)
        ->and($context->run($college, fn () => Subject::where('code', 'PHILO')->exists()))->toBeFalse()
        ->and($context->run($lycee, fn () => Stream::orderBy('code')->pluck('code')->all()))->toBe(['A', 'C', 'D'])
        ->and($context->run($lycee, fn () => Subject::where('code', 'PHILO')->exists()))->toBeTrue();
});

it('ouvre l\'année scolaire en cours avec ses trois trimestres', function () {
    [$tenant] = provisionSchool();

    app(TenantContext::class)->run($tenant, function () {
        $year = SchoolYear::query()->with('terms')->sole();

        expect($year->name)->toBe('2026-2027')
            ->and($year->status)->toBe(SchoolYearStatus::Open)
            ->and($year->starts_on->toDateString())->toBe('2026-10-01')
            ->and($year->ends_on->toDateString())->toBe('2027-07-31')
            ->and($year->terms->pluck('starts_on')->map->toDateString()->all())
            ->toBe(['2026-10-01', '2027-01-05', '2027-04-10']);
    });
});

it('ajoute un cycle plus tard sans dupliquer l\'existant', function () {
    [$tenant] = provisionSchool(cycles: ['COLLEGE']);

    app(ApplyAcademicPreset::class)->handle($tenant, ['COLLEGE', 'LYCEE']);

    app(TenantContext::class)->run($tenant, function () {
        expect(Cycle::count())->toBe(2)
            ->and(Level::where('code', '6E')->count())->toBe(1)
            ->and(Level::firstWhere('code', '3E')->nextLevel->code)->toBe('2ND');
    });
});
