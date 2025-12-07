<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'user_id' => \App\Models\User::factory(),
            'due_on' => $this->faker->dateTimeBetween('now', '+1 year'),
            'vat_percentage' => $this->faker->randomElement([0, 5, 20]),
            'is_vat_inclusive' => $this->faker->boolean,
        ];
    }
}
