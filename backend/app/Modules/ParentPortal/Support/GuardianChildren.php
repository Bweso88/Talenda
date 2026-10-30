<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Support;

use App\Models\User;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/**
 * Enfants qu'un compte parent peut consulter dans l'école active : ceux
 * dont il est responsable, par le lien que l'école a établi entre son
 * compte et sa fiche responsable (guardians.user_id).
 */
final class GuardianChildren
{
    /**
     * @return Builder<Student>
     */
    public static function of(User $user): Builder
    {
        return Student::query()->whereHas('guardians', fn (Builder $guardian) => $guardian->where('user_id', $user->id));
    }

    /**
     * Introuvable (404) plutôt qu'interdit : l'app ne révèle pas
     * l'existence des autres élèves.
     */
    public static function findOrFail(User $user, string $uuid): Student
    {
        return self::of($user)->where('uuid', $uuid)->firstOrFail();
    }
}
