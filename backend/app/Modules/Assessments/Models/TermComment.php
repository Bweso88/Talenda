<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Appréciation d'une période : d'un enseignant pour sa matière
 * (class_subject_id renseigné) ou du conseil de classe (class_subject_id nul).
 *
 * @property int $id
 * @property int $term_id
 * @property int $enrollment_id
 * @property int|null $class_subject_id
 * @property string $body
 * @property int|null $author_id
 */
#[Fillable(['term_id', 'enrollment_id', 'class_subject_id', 'body', 'author_id'])]
class TermComment extends Model
{
    use BelongsToTenant;
}
