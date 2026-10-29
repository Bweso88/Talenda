<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Support;

/**
 * Calculs de notation, sans accès à la base (docs/PRODUCT_ARCHITECTURE.md §9).
 *
 * - Moyenne d'une matière : moyenne de chaque type d'évaluation (devoirs,
 *   composition…), puis moyenne de ces moyennes pondérée par le poids du
 *   type. Un type sans note n'entre pas dans le calcul.
 * - Moyenne générale : moyenne des matières pondérée par les coefficients ;
 *   une matière sans moyenne n'entre pas dans le calcul (ni dans le total
 *   des coefficients).
 * - Arrondi au centième, au plus proche (0,005 → 0,01), appliqué une seule
 *   fois au résultat final : les moyennes intermédiaires restent exactes.
 * - Rangs : à moyenne égale (après arrondi), même rang, « ex æquo » ; le
 *   rang suivant saute d'autant (1, 2, 2, 4).
 */
final class GradeCalculator
{
    /**
     * @param  array<int|string, list<float>>  $scoresByType  notes sur 20, par type d'évaluation
     * @param  array<int|string, float>  $weights  poids de chaque type
     */
    public static function subjectAverage(array $scoresByType, array $weights): ?float
    {
        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($scoresByType as $type => $scores) {
            if ($scores === []) {
                continue;
            }

            $weight = $weights[$type] ?? 1.0;
            $weighted += (array_sum($scores) / count($scores)) * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weighted / $totalWeight : null;
    }

    /**
     * @param  list<array{average: float|null, coefficient: float}>  $subjects
     * @return array{average: float|null, points: float, coefficient: float}
     */
    public static function generalAverage(array $subjects): array
    {
        $points = 0.0;
        $coefficients = 0.0;

        foreach ($subjects as $subject) {
            if ($subject['average'] === null) {
                continue;
            }

            $points += self::round($subject['average']) * $subject['coefficient'];
            $coefficients += $subject['coefficient'];
        }

        return [
            'average' => $coefficients > 0 ? $points / $coefficients : null,
            'points' => $points,
            'coefficient' => $coefficients,
        ];
    }

    public static function round(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, float|null>  $averages
     * @return array<TKey, array{rank: int, tied: bool}> les élèves sans moyenne ne sont pas classés
     */
    public static function ranks(array $averages): array
    {
        $rounded = [];

        foreach ($averages as $key => $average) {
            if ($average !== null) {
                $rounded[$key] = self::round($average);
            }
        }

        arsort($rounded);

        $counts = array_count_values(array_map(fn (float $value): string => number_format($value, 2), $rounded));
        $ranks = [];
        $position = 0;
        $previous = null;
        $currentRank = 0;

        foreach ($rounded as $key => $value) {
            $position++;

            if ($previous === null || $value < $previous) {
                $currentRank = $position;
                $previous = $value;
            }

            $ranks[$key] = ['rank' => $currentRank, 'tied' => $counts[number_format($value, 2)] > 1];
        }

        return $ranks;
    }

    /**
     * @param  list<float>  $values
     * @return array{average: float|null, highest: float|null, lowest: float|null}
     */
    public static function stats(array $values): array
    {
        if ($values === []) {
            return ['average' => null, 'highest' => null, 'lowest' => null];
        }

        return [
            'average' => array_sum($values) / count($values),
            'highest' => max($values),
            'lowest' => min($values),
        ];
    }
}
