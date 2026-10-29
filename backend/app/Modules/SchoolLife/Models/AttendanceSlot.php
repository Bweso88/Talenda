<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Models;

use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\SchoolLife\Policies\AttendanceSlotPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Créneau d'appel de l'école (matin, après-midi, ou heure de cours).
 *
 * @property int $id
 * @property string $name
 * @property string $starts_at
 * @property string $ends_at
 * @property int $position
 */
#[UsePolicy(AttendanceSlotPolicy::class)]
#[Fillable(['name', 'starts_at', 'ends_at', 'position'])]
class AttendanceSlot extends Model implements TracksUsage
{
    use BelongsToTenant;

    /**
     * @return HasMany<AttendanceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function durationMinutes(): int
    {
        return (int) Carbon::parse($this->starts_at)->diffInMinutes(Carbon::parse($this->ends_at));
    }

    /**
     * « Matin (07:30 – 12:30) ».
     */
    public function label(): string
    {
        return $this->name.' ('.substr($this->starts_at, 0, 5).' – '.substr($this->ends_at, 0, 5).')';
    }

    public function isInUse(): bool
    {
        return $this->sessions()->exists();
    }
}
