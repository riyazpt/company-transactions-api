<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Transactions\StatusService;
use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionStatusTest extends TestCase
{
    // StatusService relies on models, and existing implemnetation calls ->payments->sum().
    // While pure Unit tests usually mock, since we are using Eloquent relations and eager loading logic, 
    // it's easier to use Kernel/Database or Mockery. 
    // However, since StatusService logic is simple but relies on eager loading check ($transaction->payments collection), 
    // we should ensure the collection is populated.
    
    // We will use standard TestCase which boots the app, allowing model instantiation.
    // If we want purely isolated unit tests, we'd mock the Transaction model, but mocking magic properties like collections is painful.
    // Let's use simple logic checks.

    use RefreshDatabase;

    protected StatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StatusService();
    }

    public function test_it_returns_paid_when_payments_cover_amount()
    {
        // Amount 100, VAT 20% => Total 120
        $transaction = Transaction::factory()->make([
            'amount' => 100,
            'vat_percentage' => 20,
            'is_vat_inclusive' => false,
            'due_on' => now()->addDay(),
        ]);
          

        
        // Mocking the payments relationship is tricky on a clean model.
        // It's easier to save it to DB or set relations manually.
        // Let's set relation manually to avoid DB hit for a "Unit" test if possible, 
        // but since we extended TestCase, might as well use DB for simplicity and reliability.
        
        $transaction->save();
        
        // Add payment
        $transaction->payments()->create([
            'amount' => 120,
            'paid_on' => now(),
        ]);
        
        // Refresh to load relation
        $transaction->load('payments');

        $status = $this->service->getStatus($transaction);

        $this->assertEquals('paid', $status);
    }

    public function test_it_returns_outstanding_when_not_paid_and_due_date_future()
    {
        $transaction = Transaction::factory()->create([
            'amount' => 100,
            'vat_percentage' => 20, // Total 120
            'is_vat_inclusive' => false,
            'due_on' => now()->addDay(),
        ]);
        
        $transaction->load('payments');

        $status = $this->service->getStatus($transaction);

        $this->assertEquals('outstanding', $status);
    }

    public function test_it_returns_overdue_when_not_paid_and_due_date_past()
    {
        $transaction = Transaction::factory()->create([
            'amount' => 100,
            'vat_percentage' => 20,
            'is_vat_inclusive' => false,
            'due_on' => now()->subDay(),
        ]);

        $transaction->load('payments');

        $status = $this->service->getStatus($transaction);

        $this->assertEquals('overdue', $status);
    }
    
    public function test_vat_inclusive_calculation()
    {
        $transaction = new Transaction([
            'amount' => 120,
            'vat_percentage' => 20,
            'is_vat_inclusive' => true,
        ]);
        
        $total = $this->service->getTotalWithVat($transaction);
        
        $this->assertEquals(120, $total);
    }

    public function test_vat_exclusive_calculation()
    {
        $transaction = new Transaction([
            'amount' => 100,
            'vat_percentage' => 20,
            'is_vat_inclusive' => false,
        ]);
        
        $total = $this->service->getTotalWithVat($transaction);
        
        $this->assertEquals(120, $total);
    }
}
