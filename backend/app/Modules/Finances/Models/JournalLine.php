<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ligne d'une écriture : un compte, au débit ou au crédit.
 *
 * @property int $id
 * @property int $journal_entry_id
 * @property string $account_code
 * @property string $account_label
 * @property int|null $cash_register_id
 * @property int $debit
 * @property int $credit
 * @property JournalEntry $journalEntry
 * @property CashRegister|null $cashRegister
 */
#[Fillable(['journal_entry_id', 'account_code', 'account_label', 'cash_register_id', 'debit', 'credit'])]
class JournalLine extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une écriture comptable ne se modifie pas.'));
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }
}
