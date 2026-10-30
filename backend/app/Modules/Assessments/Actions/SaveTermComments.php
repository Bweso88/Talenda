<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Actions;

use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Models\TermComment;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Appréciations d'une période : de l'enseignant pour sa matière
 * ($classSubject renseigné) ou du conseil de classe ($classSubject nul).
 * Modifiables jusqu'à la publication des bulletins.
 */
final class SaveTermComments
{
    /**
     * @param  array<int, string|null>  $comments  par id d'inscription
     */
    public function handle(Term $term, ?ClassSubject $classSubject, array $comments, User $author): void
    {
        if ($term->status === TermStatus::Published) {
            throw new BusinessRuleViolation('Les bulletins de cette période sont publiés : les appréciations ne se modifient plus.');
        }

        DB::transaction(function () use ($term, $classSubject, $comments, $author): void {
            foreach ($comments as $enrollmentId => $body) {
                $keys = ['term_id' => $term->id, 'enrollment_id' => (int) $enrollmentId, 'class_subject_id' => $classSubject?->id];

                if (blank($body)) {
                    TermComment::query()->where($keys)->delete();

                    continue;
                }

                TermComment::query()->updateOrCreate($keys, [
                    'body' => mb_substr(trim((string) $body), 0, 500),
                    'author_id' => $author->id,
                ]);
            }
        });
    }
}
