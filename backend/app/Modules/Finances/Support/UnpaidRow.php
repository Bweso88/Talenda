<?php

declare(strict_types=1);

namespace App\Modules\Finances\Support;

use App\Modules\Finances\Models\Invoice;

/**
 * Une ligne de l'état des impayés.
 */
final class UnpaidRow
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $student,
        public readonly string $registrationNumber,
        public readonly string $class,
        public readonly ?string $contact,
        public readonly ?string $phone,
        public readonly int $total,
        public readonly int $paid,
        public readonly int $balance,
        public readonly int $overdue,
    ) {}

    /**
     * @return array{invoice_id: int, student: string, registration_number: string, class: string, contact: string|null, phone: string|null, total: int, paid: int, balance: int, overdue: int}
     */
    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'student' => $this->student,
            'registration_number' => $this->registrationNumber,
            'class' => $this->class,
            'contact' => $this->contact,
            'phone' => $this->phone,
            'total' => $this->total,
            'paid' => $this->paid,
            'balance' => $this->balance,
            'overdue' => $this->overdue,
        ];
    }
}
