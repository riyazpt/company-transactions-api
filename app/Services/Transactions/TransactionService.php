<?php

namespace App\Services\Transactions;

use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Repositories\PaymentRepository;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(
        protected TransactionRepository $transactions,
        protected PaymentRepository $payments,
        protected StatusService $statusService,
    ) {}

    public function create(array $data): Transaction
    {
        return $this->transactions->create($data);
    }

    public function addPayment(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $this->payments->create([
                'transaction_id' => $transaction->id,
                'amount'         => $data['amount'],
                'paid_on'        => $data['paid_on'],
                'details'        => $data['details'] ?? null,
            ]);

            return $transaction->refresh()->load('payments');
        });
    }

    public function computeStatus(Transaction $transaction): string
    {
        return $this->statusService->getStatus($transaction);
    }
}
