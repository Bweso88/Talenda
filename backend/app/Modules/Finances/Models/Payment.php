<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Enums\PaymentStatus;
use App\Modules\Finances\Policies\PaymentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Encaissement. Jamais supprimé ; seule l'annulation (statut, date,
 * auteur, motif) est permise après coup.
 *
 * @property int $id
 * @property string $uuid
 * @property int $invoice_id
 * @property int|null $cash_session_id
 * @property string $receipt_number
 * @property int $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property string|null $payer_name
 * @property Carbon $paid_on
 * @property int $recorded_by
 * @property PaymentStatus $status
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 * @property Carbon $created_at
 * @property Invoice $invoice
 * @property CashSession|null $cashSession
 * @property User $recorder
 * @property User|null $reverser
 */
#[UsePolicy(PaymentPolicy::class)]
#[Fillable(['invoice_id', 'cash_session_id', 'receipt_number', 'amount', 'method', 'reference', 'payer_name', 'paid_on', 'recorded_by', 'status', 'reversed_at', 'reversed_by', 'reversal_reason'])]
class Payment extends Model
{
    use BelongsToTenant;
    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'valid',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_on' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Seule l'annulation peut modifier un paiement enregistré.
        static::updating(function (Payment $payment): void {
            $allowed = ['status', 'reversed_at', 'reversed_by', 'reversal_reason', 'updated_at'];

            if (array_diff(array_keys($payment->getDirty()), $allowed) !== [] || $payment->getOriginal('status') !== PaymentStatus::Valid) {
                throw new LogicException('Un paiement ne se modifie pas : annulez-le puis enregistrez-en un nouveau.');
            }
        });
        static::deleting(fn () => throw new LogicException('Un paiement ne se supprime pas : annulez-le.'));
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
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
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('status', PaymentStatus::Valid);
    }

    public function isValid(): bool
    {
        return $this->status === PaymentStatus::Valid;
    }
}
