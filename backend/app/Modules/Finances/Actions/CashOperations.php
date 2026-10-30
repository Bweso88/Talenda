<?php

declare(strict_types=1);

namespace App\Modules\Finances\Actions;

use App\Models\User;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Support\SequenceGenerator;
use App\Modules\Finances\Enums\CashOperationStatus;
use App\Modules\Finances\Enums\CashOperationType;
use App\Modules\Finances\Models\CashOperation;
use App\Modules\Finances\Models\CashRegister;
use App\Modules\Finances\Models\ExpenseCategory;
use App\Modules\Finances\Models\TreasurySetting;
use App\Modules\Finances\Support\Ledger;
use App\Modules\Finances\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sorties et mouvements d'argent des caisses (docs/TRESORERIE.md) :
 * dépenses, salaires, avances à justifier, virements entre caisses.
 *
 * Règles :
 * - une caisse ne descend jamais sous zéro ;
 * - au-delà du seuil de l'école, une sortie attend la validation de la
 *   direction (qui ne valide jamais sa propre demande) ;
 * - chaque mouvement passe une écriture SYSCOHADA au journal ;
 * - rien ne s'efface : une erreur s'annule par une écriture inverse.
 */
final class CashOperations
{
    public function __construct(
        private readonly SequenceGenerator $sequenceGenerator,
        private readonly RecordAudit $recordAudit,
        private readonly Ledger $ledger,
    ) {}

    /**
     * Caisses dont la personne peut sortir de l'argent : toutes pour la
     * direction et la comptabilité, sinon celle qu'elle tient (session
     * ouverte).
     *
     * @return Collection<int, CashRegister>
     */
    public static function registersFor(User $user): Collection
    {
        if ($user->can('treasury.manage')) {
            return CashRegister::query()->where('is_active', true)->orderBy('name')->get();
        }

        $session = CashDesk::openSessionOf($user);

        return CashRegister::query()->whereKey($session?->cash_register_id)->get();
    }

    public function expense(CashRegister $register, ExpenseCategory $category, int $amount, string $description, User $actor, ?string $reference = null, ?CarbonInterface $occurredOn = null): CashOperation
    {
        return $this->create(CashOperationType::Expense, $register, $amount, $description, $actor, $occurredOn, [
            'expense_category_id' => $category->id,
            'reference' => blank($reference) ? null : trim($reference),
        ]);
    }

    /**
     * Paiement d'un salaire (sans calcul de paie) : montant net versé au
     * membre du personnel pour un mois.
     */
    public function salary(CashRegister $register, StaffMember $staffMember, string $period, int $amount, User $actor, ?string $description = null, ?CarbonInterface $occurredOn = null): CashOperation
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            throw new BusinessRuleViolation('Indiquez le mois payé (ex. 2026-11).');
        }

        $alreadyPaid = CashOperation::query()
            ->where('type', CashOperationType::Salary)
            ->where('staff_member_id', $staffMember->id)
            ->where('period', $period)
            ->whereIn('status', [CashOperationStatus::Done, CashOperationStatus::PendingApproval])
            ->exists();

        if ($alreadyPaid && blank($description)) {
            throw new BusinessRuleViolation("Le salaire de {$staffMember->full_name} pour {$period} est déjà enregistré : précisez le motif d'un second versement (complément, rappel…).");
        }

        return $this->create(CashOperationType::Salary, $register, $amount, $description ?? "Salaire {$period} — {$staffMember->full_name}", $actor, $occurredOn, [
            'staff_member_id' => $staffMember->id,
            'period' => $period,
        ]);
    }

    /**
     * Avance à justifier : argent remis pour un achat ; la personne revient
     * avec la facture et la monnaie (settleAdvance).
     */
    public function advance(CashRegister $register, ExpenseCategory $category, int $amount, string $description, User $actor, ?StaffMember $staffMember = null, ?string $beneficiary = null, ?CarbonInterface $occurredOn = null): CashOperation
    {
        if ($staffMember === null && blank($beneficiary)) {
            throw new BusinessRuleViolation('Indiquez à qui l\'argent est remis.');
        }

        return $this->create(CashOperationType::Advance, $register, $amount, $description, $actor, $occurredOn, [
            'expense_category_id' => $category->id,
            'staff_member_id' => $staffMember?->id,
            'beneficiary' => $staffMember === null ? trim((string) $beneficiary) : null,
        ]);
    }

    /**
     * Versement d'une caisse à une autre (site → caisse centrale, caisse →
     * banque). L'argent est « en route » jusqu'à sa réception.
     */
    public function transfer(CashRegister $from, CashRegister $to, int $amount, User $actor, ?string $description = null, ?CarbonInterface $occurredOn = null): CashOperation
    {
        if ($from->is($to)) {
            throw new BusinessRuleViolation('Choisissez une caisse de destination différente.');
        }

        if (! $to->is_active) {
            throw new BusinessRuleViolation("La caisse « {$to->name} » est désactivée.");
        }

        return $this->create(CashOperationType::Transfer, $from, $amount, $description ?? "Versement {$from->name} → {$to->name}", $actor, $occurredOn, [
            'destination_register_id' => $to->id,
        ]);
    }

    public function approve(CashOperation $operation, User $approver): CashOperation
    {
        $this->assertStatus($operation, CashOperationStatus::PendingApproval);
        $this->assertApprover($operation, $approver);

        return DB::transaction(function () use ($operation, $approver): CashOperation {
            $operation = $this->locked($operation);
            $operation->update(['approved_by' => $approver->id, 'approved_at' => now()]);
            $this->execute($operation, $approver);
            $this->recordAudit->handle('treasury.approved', actor: $approver, subject: $operation, metadata: ['number' => $operation->number]);

            return $operation;
        });
    }

    public function reject(CashOperation $operation, string $reason, User $approver): CashOperation
    {
        $this->assertStatus($operation, CashOperationStatus::PendingApproval);
        $this->assertApprover($operation, $approver);
        $this->assertReason($reason, 'du refus');

        $operation->update([
            'status' => CashOperationStatus::Rejected,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => trim($reason),
        ]);
        $this->recordAudit->handle('treasury.rejected', actor: $approver, subject: $operation, metadata: ['number' => $operation->number, 'reason' => $reason]);

        return $operation;
    }

    /**
     * Retour d'une avance : montant dépensé (facture à l'appui) et monnaie
     * rendue. Dépensé + rendu = avance ; si l'achat a coûté plus que
     * l'avance, la caisse rembourse la différence. Achat annulé : tout est
     * rendu (dépensé 0).
     */
    public function settleAdvance(CashOperation $advance, int $spent, int $returned, User $actor, ?string $note = null, ?ExpenseCategory $category = null): CashOperation
    {
        if ($advance->type !== CashOperationType::Advance) {
            throw new BusinessRuleViolation('Seule une avance se justifie.');
        }

        $this->assertStatus($advance, CashOperationStatus::Outstanding);

        if ($spent < 0 || $returned < 0) {
            throw new BusinessRuleViolation('Les montants ne peuvent pas être négatifs.');
        }

        $extra = $spent - $advance->amount;

        if ($spent + $returned !== $advance->amount && ! ($returned === 0 && $extra > 0)) {
            throw new BusinessRuleViolation('Dépensé ('.Money::format($spent).') et rendu ('.Money::format($returned).') doivent faire l\'avance de '.Money::format($advance->amount).'.');
        }

        $register = $advance->cashRegister()->firstOrFail();
        $this->assertCanUse($register, $actor);

        if ($extra > 0 && blank($note)) {
            throw new BusinessRuleViolation('L\'achat a dépassé l\'avance de '.Money::format($extra).' : expliquez-le.');
        }

        $category ??= $advance->expenseCategory()->firstOrFail();

        return DB::transaction(function () use ($advance, $spent, $returned, $actor, $note, $category, $register, $extra): CashOperation {
            $advance = $this->locked($advance);

            if ($extra > 0) {
                $this->assertBalance($register, $extra);
            }

            [$advancesCode, $advancesLabel] = Ledger::account('advances');
            $this->ledger->record(now(), $advance->number, "Justification de l'avance — {$advance->description}", $advance, $actor, [
                Ledger::debit($category->account_code, $category->name, $spent),
                Ledger::register($register, $returned, 0),
                Ledger::credit($advancesCode, "{$advancesLabel} — {$advance->beneficiaryName()}", $advance->amount),
                Ledger::register($register, 0, max(0, $extra)),
            ]);

            $advance->update([
                'status' => CashOperationStatus::Settled,
                'spent_amount' => $spent,
                'returned_amount' => $returned,
                'expense_category_id' => $category->id,
                'settled_by' => $actor->id,
                'settled_at' => now(),
                'settlement_note' => blank($note) ? null : trim($note),
            ]);
            $this->recordAudit->handle('treasury.advance_settled', actor: $actor, subject: $advance, metadata: ['number' => $advance->number, 'spent' => $spent, 'returned' => $returned]);

            return $advance;
        });
    }

    /**
     * Réception d'un virement : la caisse qui reçoit compte l'argent ; un
     * écart avec le montant envoyé est expliqué et passé au journal.
     */
    public function receiveTransfer(CashOperation $transfer, int $received, User $actor, ?string $note = null): CashOperation
    {
        $this->assertStatus($transfer, CashOperationStatus::InTransit);
        $destination = $transfer->destinationRegister()->firstOrFail();
        $this->assertCanUse($destination, $actor);

        if ($received < 0) {
            throw new BusinessRuleViolation('Le montant reçu ne peut pas être négatif.');
        }

        $difference = $received - $transfer->amount;

        if ($difference !== 0 && blank($note)) {
            throw new BusinessRuleViolation('Écart de '.Money::format($difference).' avec le montant envoyé : expliquez-le.');
        }

        return DB::transaction(function () use ($transfer, $received, $actor, $note, $destination, $difference): CashOperation {
            $transfer = $this->locked($transfer);
            [$transfersCode, $transfersLabel] = Ledger::account('transfers');

            $this->ledger->record(now(), $transfer->number, "Réception — {$transfer->description}".($difference === 0 ? '' : ' ('.trim((string) $note).')'), $transfer, $actor, [
                Ledger::register($destination, $received, 0),
                Ledger::credit($transfersCode, $transfersLabel, $transfer->amount),
                ...($difference === 0 ? [] : $this->transitDifference($difference)),
            ]);

            $transfer->update([
                'status' => CashOperationStatus::Received,
                'received_amount' => $received,
                'received_by' => $actor->id,
                'received_at' => now(),
                'settlement_note' => blank($note) ? null : trim($note),
            ]);
            $this->recordAudit->handle('treasury.transfer_received', actor: $actor, subject: $transfer, metadata: ['number' => $transfer->number, 'received' => $received]);

            return $transfer;
        });
    }

    /**
     * Annulation : virement pas encore reçu, ou dépense / salaire payés par
     * erreur (l'argent revient en caisse). Jamais par la personne qui l'a
     * saisi, sauf un virement encore en route.
     */
    public function reverse(CashOperation $operation, string $reason, User $actor): CashOperation
    {
        $this->assertReason($reason, 'de l\'annulation');

        if ($operation->type === CashOperationType::Advance) {
            throw new BusinessRuleViolation('Une avance ne s\'annule pas : enregistrez son retour (tout rendu).');
        }

        $isTransfer = $operation->type === CashOperationType::Transfer;
        $this->assertStatus($operation, $isTransfer ? CashOperationStatus::InTransit : CashOperationStatus::Done);

        if ($isTransfer ? ($operation->created_by !== $actor->id && ! $actor->can('treasury.approve')) : ! $actor->can('treasury.approve')) {
            throw new BusinessRuleViolation('Seule la direction peut annuler cette opération.');
        }

        if (! $isTransfer && $operation->created_by === $actor->id) {
            throw new BusinessRuleViolation('Vous ne pouvez pas annuler une opération que vous avez vous-même saisie.');
        }

        return DB::transaction(function () use ($operation, $reason, $actor, $isTransfer): CashOperation {
            $operation = $this->locked($operation);
            $this->ledger->reverse($operation, trim($reason), $actor);

            $operation->update([
                'status' => $isTransfer ? CashOperationStatus::Cancelled : CashOperationStatus::Reversed,
                'reversed_by' => $actor->id,
                'reversed_at' => now(),
                'reversal_reason' => trim($reason),
            ]);
            $this->recordAudit->handle('treasury.reversed', actor: $actor, subject: $operation, metadata: ['number' => $operation->number, 'reason' => $reason]);

            return $operation;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(CashOperationType $type, CashRegister $register, int $amount, string $description, User $actor, ?CarbonInterface $occurredOn, array $attributes): CashOperation
    {
        $occurredOn ??= now();

        if ($amount <= 0) {
            throw new BusinessRuleViolation('Le montant doit être positif.');
        }

        if ($occurredOn->isFuture()) {
            throw new BusinessRuleViolation('La date ne peut pas être dans le futur.');
        }

        if (blank($description)) {
            throw new BusinessRuleViolation('Indiquez l\'objet de l\'opération.');
        }

        if (! $register->is_active) {
            throw new BusinessRuleViolation("La caisse « {$register->name} » est désactivée.");
        }

        $this->assertCanUse($register, $actor);

        $threshold = TreasurySetting::current()->approval_threshold;
        $needsApproval = $type->needsApprovalAboveThreshold() && $amount > $threshold && ! $actor->can('treasury.approve');

        return DB::transaction(function () use ($type, $register, $amount, $description, $actor, $occurredOn, $attributes, $needsApproval): CashOperation {
            $operation = CashOperation::create([
                ...$attributes,
                'number' => sprintf('OP%d-%05d', $occurredOn->year, $this->sequenceGenerator->next("cash_operation:{$occurredOn->year}")),
                'type' => $type,
                'status' => CashOperationStatus::PendingApproval,
                'cash_register_id' => $register->id,
                'amount' => $amount,
                'description' => mb_substr(trim($description), 0, 255),
                'occurred_on' => $occurredOn,
                'created_by' => $actor->id,
            ]);

            if (! $needsApproval) {
                $this->execute($operation, $actor);
            }

            $this->recordAudit->handle('treasury.recorded', actor: $actor, subject: $operation, metadata: [
                'number' => $operation->number,
                'type' => $type->value,
                'amount' => $amount,
                'register' => $register->name,
                'pending_approval' => $needsApproval,
            ]);

            return $operation;
        });
    }

    /**
     * L'argent sort de la caisse : écriture au journal et nouvel état.
     */
    private function execute(CashOperation $operation, User $actor): void
    {
        $register = $operation->cashRegister()->lockForUpdate()->firstOrFail();
        $this->assertBalance($register, $operation->amount);

        $lines = match ($operation->type) {
            CashOperationType::Expense => $this->expenseLines($operation, $register),
            CashOperationType::Salary => $this->salaryLines($operation, $register),
            CashOperationType::Advance => $this->advanceLines($operation, $register),
            CashOperationType::Transfer => $this->transferLines($operation, $register),
        };

        $this->ledger->record($operation->occurred_on, $operation->number, $operation->description, $operation, $actor, $lines);

        $operation->update(['status' => match ($operation->type) {
            CashOperationType::Expense, CashOperationType::Salary => CashOperationStatus::Done,
            CashOperationType::Advance => CashOperationStatus::Outstanding,
            CashOperationType::Transfer => CashOperationStatus::InTransit,
        }]);
    }

    /**
     * @return list<array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}>
     */
    private function expenseLines(CashOperation $operation, CashRegister $register): array
    {
        $category = $operation->expenseCategory()->firstOrFail();

        return [Ledger::debit($category->account_code, $category->name, $operation->amount), Ledger::register($register, 0, $operation->amount)];
    }

    /**
     * Charge de personnel (661) et paiement de la rémunération due (422).
     *
     * @return list<array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}>
     */
    private function salaryLines(CashOperation $operation, CashRegister $register): array
    {
        [$salariesCode, $salariesLabel] = Ledger::account('salaries');
        [$dueCode, $dueLabel] = Ledger::account('salaries_due');
        $name = $operation->staffMember()->firstOrFail()->full_name;

        return [
            Ledger::debit($salariesCode, $salariesLabel, $operation->amount),
            Ledger::credit($dueCode, "{$dueLabel} — {$name}", $operation->amount),
            Ledger::debit($dueCode, "{$dueLabel} — {$name}", $operation->amount),
            Ledger::register($register, 0, $operation->amount),
        ];
    }

    /**
     * @return list<array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}>
     */
    private function advanceLines(CashOperation $operation, CashRegister $register): array
    {
        [$code, $label] = Ledger::account('advances');
        $operation->loadMissing('staffMember');

        return [Ledger::debit($code, "{$label} — {$operation->beneficiaryName()}", $operation->amount), Ledger::register($register, 0, $operation->amount)];
    }

    /**
     * @return list<array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}>
     */
    private function transferLines(CashOperation $operation, CashRegister $register): array
    {
        [$code, $label] = Ledger::account('transfers');

        return [Ledger::debit($code, $label, $operation->amount), Ledger::register($register, 0, $operation->amount)];
    }

    /**
     * Écart entre l'envoyé et le reçu d'un virement (manquant 658,
     * excédent 758), soldé contre le compte de virements (585).
     *
     * @return list<array{account: string, label: string, debit: int, credit: int, register: CashRegister|null}>
     */
    private function transitDifference(int $difference): array
    {
        if ($difference < 0) {
            [$code, $label] = Ledger::account('cash_shortage');

            return [Ledger::debit($code, $label, -$difference)];
        }

        [$code, $label] = Ledger::account('cash_surplus');

        return [Ledger::credit($code, $label, $difference)];
    }

    private function assertCanUse(CashRegister $register, User $actor): void
    {
        if ($actor->can('treasury.manage')) {
            return;
        }

        if (! $actor->can('treasury.operate') || CashDesk::openSessionOf($actor)?->cash_register_id !== $register->id) {
            throw new BusinessRuleViolation("Vous ne tenez pas la caisse « {$register->name} » : ouvrez-la d'abord.");
        }
    }

    private function assertBalance(CashRegister $register, int $amount): void
    {
        $balance = $register->balance();

        if ($amount > $balance) {
            throw new BusinessRuleViolation("Solde insuffisant dans « {$register->name} » : ".Money::format($balance).' disponibles.');
        }
    }

    private function assertApprover(CashOperation $operation, User $approver): void
    {
        if (! $approver->can('treasury.approve')) {
            throw new BusinessRuleViolation('Seule la direction peut valider une sortie d\'argent au-delà du seuil.');
        }

        if ($operation->created_by === $approver->id) {
            throw new BusinessRuleViolation('Vous ne pouvez pas valider votre propre demande.');
        }
    }

    private function assertStatus(CashOperation $operation, CashOperationStatus $expected): void
    {
        if ($operation->status !== $expected) {
            throw new BusinessRuleViolation("L'opération {$operation->number} est « {$operation->status->getLabel()} » : action impossible.");
        }
    }

    private function assertReason(string $reason, string $what): void
    {
        if (blank($reason)) {
            throw new BusinessRuleViolation("Indiquez le motif {$what}.");
        }
    }

    /**
     * Verrou sur l'opération : deux personnes ne la traitent pas en même
     * temps (double validation, double réception).
     */
    private function locked(CashOperation $operation): CashOperation
    {
        $fresh = CashOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();

        if ($fresh->status !== $operation->status) {
            throw new BusinessRuleViolation("L'opération {$operation->number} vient d'être traitée par quelqu'un d'autre.");
        }

        return $operation;
    }
}
