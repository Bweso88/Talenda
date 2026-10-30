<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Période d'évaluation d'une année (trimestre ou semestre).
 *
 * @property int $id
 * @property int $school_year_id
 * @property string $name
 * @property int $position
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property TermStatus $status
 */
#[Fillable(['school_year_id', 'name', 'position', 'starts_on', 'ends_on', 'status'])]
class Term extends Model
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => TermStatus::class,
        ];
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
