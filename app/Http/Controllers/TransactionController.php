<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Transactions\TransactionService;
use App\Repositories\TransactionRepository;
use App\Services\Transactions\StatusService;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactions,
        protected TransactionRepository $transactionRepo,
        protected StatusService $statusService
    ) {}

    // ----------------------------------------------------
    // 1. Create Transaction (Admin Only)
    // ----------------------------------------------------
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'amount'           => 'required|numeric|min:0',
            'payer_id'         => 'required|exists:users,id',
            'due_on'           => 'required|date',
            'vat_percentage'   => 'required|numeric|min:0',
            'is_vat_inclusive' => 'required|boolean',
        ]);

        $transaction = $this->transactions->create([
            'amount'           => $validated['amount'],
            'user_id'          => $validated['payer_id'],
            'due_on'           => $validated['due_on'],
            'vat_percentage'   => $validated['vat_percentage'],
            'is_vat_inclusive' => $validated['is_vat_inclusive'],
        ]);

        return response()->json([
            'transaction' => $transaction,
            'status'      => $this->statusService->getStatus($transaction),
        ], 201);
    }

    // ----------------------------------------------------
    // 2. Record Payment (Admin Only)
    // ----------------------------------------------------
    public function addPayment(Request $request, $transactionId)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'amount'  => 'required|numeric|min:0',
            'paid_on' => 'required|date',
            'details' => 'nullable|string',
        ]);

        $transaction = $this->transactionRepo->find($transactionId);

        if (! $transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $transaction = $this->transactions->addPayment($transaction, $validated);

        return response()->json([
            'transaction' => $transaction,
            'status'      => $this->statusService->getStatus($transaction),
        ]);
    }

    // ----------------------------------------------------
    // 3. List Transactions
    // Admin → all
    // Customer → only their own
    // ----------------------------------------------------
    public function index(Request $request)
    {
        $user = $request->user();

        $isAdmin = $user->role === 'admin';

        // Admin → all, Customer → only own
        $query = $this->transactionRepo->queryForUser($user->id, $isAdmin);

        // Eager load payments to avoid N+1 in StatusService (which now sums the collection)
        // Repo already does with(['payments', 'user']), so we are good.
        $transactions = $query->orderByDesc('due_on')->get();

        $data = $transactions->map(function ($t) {
            $status = $this->statusService->getStatus($t);
            $totalDue = $this->statusService->getTotalWithVat($t);
            $totalPaid = $t->payments->sum('amount');
            $remaining = max($totalDue - $totalPaid, 0);

            return [
                'id'            => $t->id,
                'payer_id'      => $t->user_id,
                'payer_name'    => $t->user?->name,
                'amount'        => $t->amount,
                'vat_percentage' => $t->vat_percentage,
                'is_vat_inclusive' => $t->is_vat_inclusive,
                'due_on'        => $t->due_on->toDateString(),
                'status'        => $status,
                'total_due'     => $totalDue,
                'total_paid'    => $totalPaid,
                'remaining'     => $remaining,
            ];
        });

        return response()->json($data);
    }


    // ----------------------------------------------------
    // 4. Show Transaction
    // ----------------------------------------------------
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $transaction = $this->transactionRepo->find($id);

        if (! $transaction) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Customer can only see their own transactions
        if ($user->role === 'customer' && $transaction->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $status     = $this->statusService->getStatus($transaction);
        $totalDue   = $this->statusService->getTotalWithVat($transaction);
        $totalPaid  = $transaction->payments->sum('amount');
        $remaining  = max($totalDue - $totalPaid, 0);

        return response()->json([
            'id'            => $transaction->id,
            'payer_id'      => $transaction->user_id,
            'payer_name'    => $transaction->user?->name,
            'amount'        => $transaction->amount,
            'vat_percentage'=> $transaction->vat_percentage,
            'is_vat_inclusive' => $transaction->is_vat_inclusive,
            'due_on'        => $transaction->due_on->toDateString(),
            'status'        => $status,
            'total_due'     => $totalDue,
            'total_paid'    => $totalPaid,
            'remaining'     => $remaining,
            'payments'      => $transaction->payments->map(function ($p) {
                return [
                    'id'      => $p->id,
                    'amount'  => $p->amount,
                    'paid_on' => $p->paid_on->toDateString(),
                    'details' => $p->details,
                ];
            }),
        ]);
    }

    public function monthlyReport(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end   = Carbon::parse($validated['end_date'])->endOfDay();
        $now   = now();

        // Load transactions in date range, with payments
        // We use eager loading here to prevent N+1
        $transactions = Transaction::with('payments')
            ->whereBetween('due_on', [$start, $end])
            ->get();

        $buckets = [];

        foreach ($transactions as $t) {
            $month = $t->due_on->format('n');  // 1..12
            $year  = $t->due_on->format('Y');
            $key   = $year . '-' . $month;

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'month'       => $month,
                    'year'        => $year,
                    'paid'        => 0.0,
                    'outstanding' => 0.0,
                    'overdue'     => 0.0,
                ];
            }

            $totalDue  = $this->statusService->getTotalWithVat($t);
            $totalPaid = $t->payments->sum('amount'); // Collection sum

            // If the transaction is PAID, we add its full value to 'paid'?
            // Or do we add the paid amount? The prompt example has "paid":"20000".
            // I will assume it means "Total volume of paid transactions" vs "Total volume outstanding" etc.

            // However, the current code was:
            // $buckets[$key]['paid'] += $totalPaid;
            // If it is fully paid, maybe we should count the Total Due as paid?
            // Let's keep existing logic: add $totalPaid to 'paid' bucket.
            // REFACTOR: Add any paid amount to 'paid', and the rest to outstanding/overdue.
            $buckets[$key]['paid'] += $totalPaid;

            if ($totalPaid < $totalDue) { // Only if not fully paid, consider outstanding/overdue
                $remaining = max($totalDue - $totalPaid, 0);

                if ($t->due_on->isFuture() || $t->due_on->isSameDay($now)) {
                    $buckets[$key]['outstanding'] += $remaining;
                } else {
                    $buckets[$key]['overdue'] += $remaining;
                }
            }
        }

        // Normalize to simple array
        $result = array_values($buckets);

        return response()->json($result);
    }
}
