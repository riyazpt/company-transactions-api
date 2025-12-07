<?php

namespace App\Services\Transactions;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

class StatusService
{
    public function getStatus(Transaction $transaction, ?Carbon $now = null): string
    {
        $now = $now ?: now();

        $totalDue  = $this->getTotalWithVat($transaction);
        // Use the collection if loaded, otherwise it will lazily load (but we plan to eager load)
        $totalPaid = $transaction->payments->sum('amount');

        if ($totalPaid >= $totalDue) {
            return 'paid';
        }

        // We need to compare dates.
        // If due_on is today or future => outstanding
        // If due_on is past => overdue
        if ($transaction->due_on->isFuture() || $transaction->due_on->isSameDay($now)) {
            return 'outstanding';
        }

        return 'overdue';
    }

    public function getTotalWithVat(Transaction $transaction): float
    {
        $amount = (float) $transaction->amount;
        $vat    = (float) $transaction->vat_percentage;

        if ($transaction->is_vat_inclusive) {
            // amount already includes VAT
            return $amount;
        }

        return $amount + ($amount * $vat / 100);
    }
}
