<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;

class TransactionFlowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_login_returns_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_admin_can_create_transaction()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user  = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($admin)
            ->postJson('/api/transactions', [
                'amount' => 100,
                'payer_id' => $user->id,
                'due_on' => now()->addDays(10)->toDateString(),
                'vat_percentage' => 20,
                'is_vat_inclusive' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('transaction.amount', 100);
            
        $this->assertDatabaseHas('transactions', ['amount' => 100, 'user_id' => $user->id]);
    }

    public function test_customer_cannot_create_transaction()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->postJson('/api/transactions', [
                'amount' => 100,
                'payer_id' => $customer->id,
                'due_on' => now()->toDateString(),
                'vat_percentage' => 20,
                'is_vat_inclusive' => false,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_record_payment()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $transaction = Transaction::factory()->create([
             'amount' => 100, 
             'vat_percentage' => 0,
             'is_vat_inclusive' => true
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/transactions/{$transaction->id}/payments", [
                'amount' => 50,
                'paid_on' => now()->toDateString(),
                'details' => 'Partial payment',
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('payments', [
            'transaction_id' => $transaction->id,
            'amount' => 50
        ]);
    }

    public function test_customer_can_view_own_transactions()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $other    = User::factory()->create(['role' => 'customer']);
        
        $t1 = Transaction::factory()->create(['user_id' => $customer->id]);
        $t2 = Transaction::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($customer)
            ->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $t1->id])
            ->assertJsonMissing(['id' => $t2->id]);
    }

    public function test_admin_can_view_all_transactions()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Transaction::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }
    
    public function test_admin_can_see_monthly_report()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Create transactions in this month
        // 1. Paid
        $t1 = Transaction::factory()->create([
            'amount' => 100, 'vat_percentage' =>0, 'is_vat_inclusive' => true,
            'due_on' => now()->startOfMonth()
        ]);
        $t1->payments()->create(['amount' => 100, 'paid_on' => now()]);
        
        // 2. Outstanding
        Transaction::factory()->create([
            'amount' => 200, 'vat_percentage' =>0, 'is_vat_inclusive' => true,
            'due_on' => now()->endOfMonth()
        ]);
        
        // 3. Overdue (last month)
        // Wait, the report is generated for a range.
        
        $start = now()->startOfMonth()->toDateString();
        $end   = now()->endOfMonth()->toDateString();

        $response = $this->actingAs($admin)
            ->getJson("/api/reports/monthly?start_date={$start}&end_date={$end}");
            
        $response->assertStatus(200);
        
        // We expect one bucket for this month/year
        $data = $response->json();
        $bucket = $data[0];
        
        $this->assertEquals(now()->format('n'), $bucket['month']);
        $this->assertEquals(100, $bucket['paid']); 
        $this->assertEquals(200, $bucket['outstanding']);
    }
}
