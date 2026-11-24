<?php

namespace Database\Factories;

use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['receipt_no' => 'RCT-'.Str::ulid(), 'membership_transaction_id' => MembershipTransaction::factory(), 'amount_cents' => 90000, 'method' => 'cash', 'paid_at' => now(), 'recorded_by' => User::factory()];
    }
}
