<?php

namespace App\Repositories;

use App\Models\Transaction;

class TransactionRepository
{
    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }

    public function find(int $id): ?Transaction
    {
        return Transaction::with(['payments', 'user'])->find($id);
    }

    public function queryForUser(int $userId, bool $isAdmin)
    {
        $query = Transaction::with(['payments', 'user']);

        if (! $isAdmin) {
            $query->where('user_id', $userId);
        }

        return $query;
    }
}
