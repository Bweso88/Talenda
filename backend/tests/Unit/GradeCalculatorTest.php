<?php

use App\Modules\Assessments\Support\GradeCalculator;

it('calcule la moyenne d\'une matière pondérée par type d\'évaluation', function () {
    // Devoirs (poids 1) : 12 et 14 → 13 ; composition (poids 2) : 10.
    $average = GradeCalculator::subjectAverage([1 => [12.0, 14.0], 2 => [10.0]], [1 => 1.0, 2 => 2.0]);

    expect(round($average, 4))->toBe(11.0);
});

it('ignore un type d\'évaluation sans note', function () {
    expect(GradeCalculator::subjectAverage([1 => [15.0], 2 => []], [1 => 1.0, 2 => 2.0]))->toBe(15.0)
        ->and(GradeCalculator::subjectAverage([], [1 => 1.0]))->toBeNull();
});

it('calcule la moyenne générale avec les coefficients, sans les matières non notées', function () {
    $general = GradeCalculator::generalAverage([
        ['average' => 12.0, 'coefficient' => 4.0],
        ['average' => 15.5, 'coefficient' => 2.0],
        ['average' => null, 'coefficient' => 3.0],
    ]);

    expect($general['coefficient'])->toBe(6.0)
        ->and($general['points'])->toBe(79.0)
        ->and(GradeCalculator::round($general['average']))->toBe(13.17);
});

it('arrondit au centième le plus proche', function () {
    expect(GradeCalculator::round(12.345))->toBe(12.35)
        ->and(GradeCalculator::round(12.344))->toBe(12.34);
});

it('classe avec des ex æquo et saute les rangs suivants', function () {
    $ranks = GradeCalculator::ranks(['a' => 14.0, 'b' => 12.004, 'c' => 12.0, 'd' => 9.5, 'e' => null]);

    expect($ranks)->toBe([
        'a' => ['rank' => 1, 'tied' => false],
        'b' => ['rank' => 2, 'tied' => true],
        'c' => ['rank' => 2, 'tied' => true],
        'd' => ['rank' => 4, 'tied' => false],
    ]);
});
