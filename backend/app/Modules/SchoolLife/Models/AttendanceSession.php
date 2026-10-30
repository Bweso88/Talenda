<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Models;

use App\Models\User;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Appel d'une classe pour un créneau d'une journée.
 *
 * @property int $id
 * @property int $school_class_id
 * @property int $attendance_slot_id
 * @property Carbon $held_on
 * @property int $duration_minutes
 * @property int|null $taken_by
 * @property SchoolClass $schoolClass
 * @property AttendanceSlot $slot
 * @property User|null $taker
 */
#[Fillable(['school_class_id', 'attendance_slot_id', 'held_on', 'duration_minutes', 'taken_by'])]
class AttendanceSession extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<AttendanceSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(AttendanceSlot::class, 'attendance_slot_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function taker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
