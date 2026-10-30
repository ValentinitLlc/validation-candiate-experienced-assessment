<?php

namespace Database\Factories;

use App\Models\Cause;
use Illuminate\Database\Eloquent\Factories\Factory;

class PledgeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cause_id' => Cause::factory(),
            'pledger_name' => $this->faker->name(),
            'pledger_email' => $this->faker->safeEmail(),
            'amount' => 50.00,
        ];
    }
}