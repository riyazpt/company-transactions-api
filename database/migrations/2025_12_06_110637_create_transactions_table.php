<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
             $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // payer (customer)
            $table->decimal('amount', 15, 2);              // base amount (before VAT if not inclusive)
            $table->decimal('vat_percentage', 5, 2);       // e.g. 5.00
            $table->boolean('is_vat_inclusive')->default(false);
            $table->date('due_on');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
