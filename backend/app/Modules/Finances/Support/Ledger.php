<?php

declare(strict_types=1);

namespace App\Modules\Finances\Support;

use App\Models\User;
use App\Modules\Finances\Enums\CashRegisterKind;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\JournalEntry;
use App\Modules\Finances\Models\JournalLine;
use App\Modules\Finances\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Journal comptable de trésorerie (SYSCOHADA révisé, docs/TRESORERIE.md).
 *
 * Chaque mouvement d'argent y passe une écriture équilibrée ; le solde
 * d'une caisse est la somme de ses lignes. Une erreur ne s'efface pas : on
 * passe l'écriture inverse.
 */
final class Ledger
{
    /**
     * Ligne au débit.
     *
     * @return array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}
     */
    public static function debit(string $account, string $label, int $amount, ?CashRegister $register = null): array
    {
        return ['account' => $account, 'label' => $label, 'debit' => $amount, 'credit' => 0, 'register' => $register];
    }

    /**
     * Ligne au crédit.
     *
     * @return array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}
     */
    public static function credit(string $account, string $label, int $amount, ?CashRegister $register = null): array
    {
        return ['account' => $account, 'label' => $label, 'debit' => 0, 'credit' => $amount, 'register' => $register];
    }

    /**
     * Mouvement d'une caisse (son compte 571, 552 ou 521).
     *
     * @return array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}
     */
    public static function register(CashRegister $register, int $debit, int $credit): array
    {
        return ['account' => $register->account_code, 'label' => $register->accountLabel(), 'debit' => $debit, 'credit' => $credit, 'register' => $register];
    }

    /**
     * Compte standard du plan (config talenda.accounting.accounts) :
     * parents, advances, salaries, transfers, cash_shortage…
     *
     * @return array{0: string, 1: string} code, libellé
     */
    public static function account(string $key): array
    {
        /** @var array{0: string, 1: string}|null $account */
        $account = config("talenda.accounting.accounts.{$key}");

        return $account ?? throw new LogicException("Compte comptable « {$key} » non configuré.");
    }

    /**
     * Écart de caisse constaté (positif : excédent, négatif : manquant).
     *
     * @return list<array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}>
     */
    public static function cashDifference(CashRegister $register, int $difference): array
    {
        if ($difference > 0) {
            [$code, $label] = self::account('cash_surplus');

            return [self::register($register, $difference, 0), self::credit($code, $label, $difference)];
        }

        [$code, $label] = self::account('cash_shortage');

        return [self::debit($code, $label, -$difference), self::register($register, 0, -$difference)];
    }

    /**
     * @param  list<array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}>  $lines
     */
    public function record(CarbonInterface $date, string $reference, string $label, ?Model $source, ?User $actor, array $lines): JournalEntry
    {
        $lines = array_values(array_filter($lines, fn (array $line): bool => $line['debit'] !== 0 || $line['credit'] !== 0));
        $debit = array_sum(array_column($lines, 'debit'));

        if ($lines === [] || $debit !== array_sum(array_column($lines, 'credit'))) {
            throw new LogicException("Écriture déséquilibrée ou vide : {$label}.");
        }

        $entry = JournalEntry::create([
            'entry_date' => $date,
            'reference' => $reference,
            'label' => mb_substr($label, 0, 255),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'created_by' => $actor?->id,
        ]);

        foreach ($lines as $line) {
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_code' => $line['account'],
                'account_label' => mb_substr($line['label'], 0, 150),
                'cash_register_id' => $line['register']?->id,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ]);
        }

        return $entry;
    }

    /**
     * Contre-passe toutes les écritures d'une pièce (paiement, opération).
     */
    public function reverse(Model $source, string $reason, ?User $actor, ?CarbonInterface $date = null): void
    {
        JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->with('lines.cashRegister')
            ->orderBy('id')
            ->get()
            ->each(fn (JournalEntry $entry) => $this->record(
                $date ?? now(),
                $entry->reference,
                "Annulation — {$entry->label} ({$reason})",
                $source,
                $actor,
                $entry->lines->map(fn (JournalLine $line): array => [
                    'account' => $line->account_code,
                    'label' => $line->account_label,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'register' => $line->cashRegister,
                ])->all(),
            ));
    }

    /**
     * Encaissement d'un parent : débit de la caisse qui reçoit l'argent,
     * crédit du compte des parents (411).
     */
    public function paymentReceived(Payment $payment, ?CashRegister $cashRegister, User $cashier): void
    {
        [$parentsCode, $parentsLabel] = self::account('parents');
        $payment->invoice->loadMissing('student');
        $register = $payment->method === PaymentMethod::Cash ? $cashRegister : self::receivingRegister($payment->method);
        $kind = CashRegisterKind::receiving($payment->method);
        $treasury = $register === null
            ? self::debit($kind->defaultAccount(), $kind->getLabel(), $payment->amount)
            : self::register($register, $payment->amount, 0);

        $this->record(
            $payment->paid_on,
            $payment->receipt_number,
            "Paiement {$payment->invoice->student->full_name} — {$payment->method->getLabel()}",
            $payment,
            $cashier,
            [$treasury, self::credit($parentsCode, $parentsLabel, $payment->amount)],
        );
    }

    /**
     * Caisse qui reçoit un paiement autre qu'en espèces : la seule caisse
     * active de cette nature, s'il y en a une seule (sinon le compte par
     * défaut, sans caisse).
     */
    public static function receivingRegister(PaymentMethod $method): ?CashRegister
    {
        $registers = CashRegister::query()
            ->where('is_active', true)
            ->where('kind', CashRegisterKind::receiving($method))
            ->limit(2)
            ->get();

        return $registers->count() === 1 ? $registers->first() : null;
    }
}
