<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Models;

use App\Models\User;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Enums\Sanction;
use App\Modules\SchoolLife\Policies\DisciplinePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Incident de discipline et sanction décidée.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property Carbon $occurred_on
 * @property string $description
 * @property Sanction $sanction
 * @property int|null $suspension_days
 * @property bool $parents_informed
 * @property int|null $reported_by
 * @property Enrollment $enrollment
 * @property User|null $reporter
 */
#[UsePolicy(DisciplinePolicy::class)]
#[Fillable(['enrollment_id', 'occurred_on', 'description', 'sanction', 'suspension_days', 'parents_informed', 'reported_by'])]
class DisciplinaryIncident extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'sanction' => Sanction::class,
            'parents_informed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
