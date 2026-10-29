<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Actions;

use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Assessments\Enums\ScoreStatus;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\Score;
use App\Modules\Assessments\Support\ComputeClassTermResults;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre les notes d'une évaluation pour toute la classe (grille de
 * saisie). Une case vide sans absence ni dispense efface la note.
 */
final class SaveScores
{
    /**
     * @param  array<int, array{value?: mixed, status?: ScoreStatus|string|null, comment?: string|null}>  $entries  par id d'inscription
     * @return int nombre de notes enregistrées
     */
    public function handle(Assessment $assessment, array $entries): int
    {
        $assessment->loadMissing(['term', 'classSubject.schoolClass']);

        if ($assessment->term->status !== TermStatus::Open) {
            throw new BusinessRuleViolation("La saisie est fermée pour « {$assessment->term->name} ».");
        }

        $allowed = ComputeClassTermResults::enrollmentsOf($assessment->term, $assessment->classSubject->schoolClass)->modelKeys();
        $max = (float) $assessment->max_score;
        $errors = [];

        foreach ($entries as $enrollmentId => $entry) {
            $value = $entry['value'] ?? null;

            if (! in_array((int) $enrollmentId, $allowed, true)) {
                throw new BusinessRuleViolation('Un élève de la saisie n\'appartient pas à cette classe.');
            }

            if (filled($value) && (! is_numeric(str_replace(',', '.', (string) $value)) || (float) str_replace(',', '.', (string) $value) < 0 || (float) str_replace(',', '.', (string) $value) > $max)) {
                $errors[] = "note « {$value} » invalide (0 à ".rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.').')';
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('Saisie refusée : '.implode(', ', array_unique($errors)).'.');
        }

        return DB::transaction(function () use ($assessment, $entries): int {
            $saved = 0;

            foreach ($entries as $enrollmentId => $entry) {
                $status = self::status($entry['status'] ?? null);
                $value = filled($entry['value'] ?? null) ? str_replace(',', '.', (string) $entry['value']) : null;
                $keys = ['assessment_id' => $assessment->id, 'enrollment_id' => (int) $enrollmentId];

                if ($status === ScoreStatus::Present && $value === null) {
                    Score::query()->where($keys)->delete();

                    continue;
                }

                Score::query()->updateOrCreate($keys, [
                    'status' => $status,
                    'value' => $status === ScoreStatus::Present ? $value : null,
                    'comment' => blank($entry['comment'] ?? null) ? null : trim((string) $entry['comment']),
                ]);
                $saved++;
            }

            return $saved;
        });
    }

    /**
     * Le statut arrive en texte (API, import) ou en énumération (formulaire).
     */
    private static function status(ScoreStatus|string|null $status): ScoreStatus
    {
        return $status instanceof ScoreStatus ? $status : (ScoreStatus::tryFrom((string) $status) ?? ScoreStatus::Present);
    }
}
