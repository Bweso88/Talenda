<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Policies\FinanceSettingsPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Catégorie de dépense (eau, électricité, fournitures…) et son compte de
 * charge SYSCOHADA. Le caissier choisit la catégorie ; le compte n'apparaît
 * que dans le journal.
 *
 * @property int $id
 * @property string $name
 * @property string $account_code
 * @property int $position
 * @property bool $is_active
 */
#[UsePolicy(FinanceSettingsPolicy::class)]
#[Fillable(['name', 'account_code', 'position', 'is_active'])]
class ExpenseCategory extends Model implements TracksUsage
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isInUse(): bool
    {
        return CashOperation::query()->where('expense_category_id', $this->id)->exists();
    }
}
