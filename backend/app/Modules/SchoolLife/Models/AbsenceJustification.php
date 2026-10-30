<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Models;

use App\Models\User;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Policies\AttendancePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Justificatif d'absence couvrant une période (certificat médical, mot
 * des parents…). Justifie toutes les absences de l'élève sur ces dates.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property JustificationReason $reason
 * @property string|null $comment
 * @property JustificationStatus $status
 * @property string $uuid
 * @property int|null $recorded_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property User|null $reviewer
 * @property Enrollment $enrollment
 * @property User|null $recorder
 */
#[UsePolicy(AttendancePolicy::class)]
#[Fillable(['enrollment_id', 'starts_on', 'ends_on', 'reason', 'comment', 'status', 'recorded_by', 'reviewed_by', 'reviewed_at'])]
class AbsenceJustification extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'accepted',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'reason' => JustificationReason::class,
            'status' => JustificationStatus::class,
            'reviewed_at' => 'datetime',
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
