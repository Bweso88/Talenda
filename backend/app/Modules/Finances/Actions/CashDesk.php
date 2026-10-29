<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\CashSession;
use App\Modules\Finances\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Ouverture et clôture de caisse (docs/PRODUCT_ARCHITECTURE.md §9).
 */
final class CashDesk
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    public static function openSessionOf(User $user): ?CashSession
    {
        return CashSession::query()
            ->whereNull('closed_at')
            ->where('opened_by', $user->id)
            ->with('cashRegister')
            ->first();
    }

    public function open(CashRegister $register, int $openingFloat, User $user): CashSession
    {
        if (! $register->is_active) {
            throw new BusinessRuleViolation("La caisse « {$register->name} » est désactivée.");
        }

        if ($openingFloat < 0) {
            throw new BusinessRuleViolation('Le fond de caisse ne peut pas être négatif.');
        }

        $mine = self::openSessionOf($user);

        if ($mine !== null) {
            throw new BusinessRuleViolation("Vous avez déjà une session ouverte sur « {$mine->cashRegister->name} ».");
        }

        $other = $register->openSession()->with('opener')->first();

        if ($other !== null) {
            throw new BusinessRuleViolation("« {$register->name} » est déjà ouverte par {$other->opener->name}.");
        }

        return DB::transaction(function () use ($register, $openingFloat, $user): CashSession {
            $session = CashSession::create([
                'cash_register_id' => $register->id,
                'opened_by' => $user->id,
                'opened_at' => now(),
                'opening_float' => $openingFloat,
            ]);

            $this->recordAudit->handle('cash.session_opened', actor: $user, subject: $session, metadata: [
                'register' => $register->name,
                'opening_float' => $openingFloat,
            ]);

            return $session;
        });
    }

    /**
     * Clôture : les espèces comptées sont comparées aux espèces attendues ;
     * tout écart doit être expliqué.
     */
    public function close(CashSession $session, int $countedCash, ?string $note, User $user): CashSession
    {
        if (! $session->isOpen()) {
            throw new BusinessRuleViolation('Cette session de caisse est déjà clôturée.');
        }

        if ($countedCash < 0) {
            throw new BusinessRuleViolation('Le montant compté ne peut pas être négatif.');
        }

        $expected = $session->currentExpectedCash();

        if ($countedCash !== $expected && blank($note)) {
            throw new BusinessRuleViolation('Écart de '.Money::format($countedCash - $expected).' : expliquez-le avant de clôturer.');
        }

        return DB::transaction(function () use ($session, $countedCash, $note, $user, $expected): CashSession {
            $session->update([
                'closed_by' => $user->id,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'closing_note' => $note,
            ]);

            $this->recordAudit->handle('cash.session_closed', actor: $user, subject: $session, metadata: [
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'difference' => $countedCash - $expected,
            ]);

            return $session;
        });
    }
}
