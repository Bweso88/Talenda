<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Enums\CashOperationStatus;
use App\Modules\Finances\Enums\CashOperationType;
use App\Modules\Finances\Policies\CashOperationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Opération de caisse (docs/TRESORERIE.md) : dépense, salaire, avance à
 * justifier ou virement entre caisses. Jamais supprimée ; seuls ses
 * changements d'état (validation, justification, réception, annulation)
 * la modifient.
 *
 * @property int $id
 * @property string $uuid
 * @property string $number
 * @property CashOperationType $type
 * @property CashOperationStatus $status
 * @property int $cash_register_id
 * @property int|null $destination_register_id
 * @property int $amount
 * @property int|null $expense_category_id
 * @property int|null $staff_member_id
 * @property string|null $beneficiary
 * @property string|null $period
 * @property string $description
 * @property string|null $reference
 * @property Carbon $occurred_on
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property int|null $received_amount
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property int|null $spent_amount
 * @property int|null $returned_amount
 * @property int|null $settled_by
 * @property Carbon|null $settled_at
 * @property string|null $settlement_note
 * @property int|null $reversed_by
 * @property Carbon|null $reversed_at
 * @property string|null $reversal_reason
 * @property Carbon $created_at
 * @property CashRegister $cashRegister
 * @property CashRegister|null $destinationRegister
 * @property ExpenseCategory|null $expenseCategory
 * @property StaffMember|null $staffMember
 * @property User $creator
 * @property User|null $approver
 */
#[UsePolicy(CashOperationPolicy::class)]
#[Fillable([
    'number', 'type', 'status', 'cash_register_id', 'destination_register_id', 'amount', 'expense_category_id',
    'staff_member_id', 'beneficiary', 'period', 'description', 'reference', 'occurred_on', 'created_by',
    'approved_by', 'approved_at', 'rejection_reason', 'received_amount', 'received_by', 'received_at',
    'spent_amount', 'returned_amount', 'settled_by', 'settled_at', 'settlement_note',
    'reversed_by', 'reversed_at', 'reversal_reason',
])]
class CashOperation extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    /**
     * Ce qui ne change jamais après l'enregistrement.
     */
    private const IMMUTABLE = ['number', 'type', 'cash_register_id', 'destination_register_id', 'amount', 'staff_member_id', 'period', 'occurred_on', 'created_by'];

    protected function casts(): array
    {
        return [
            'type' => CashOperationType::class,
            'status' => CashOperationStatus::class,
            'amount' => 'integer',
            'received_amount' => 'integer',
            'spent_amount' => 'integer',
            'returned_amount' => 'integer',
            'occurred_on' => 'date',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'settled_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CashOperation $operation): void {
            if (array_intersect(array_keys($operation->getDirty()), self::IMMUTABLE) !== []) {
                throw new LogicException('Une opération de caisse ne se modifie pas : annulez-la puis enregistrez-en une nouvelle.');
            }
        });

        static::deleting(fn () => throw new LogicException('Une opération de caisse ne se supprime pas : annulez-la.'));
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function destinationRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'destination_register_id');
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Personne qui reçoit l'argent : le membre du personnel, sinon le nom saisi.
     */
    public function beneficiaryName(): ?string
    {
        return $this->staffMember->full_name ?? $this->beneficiary;
    }
}
