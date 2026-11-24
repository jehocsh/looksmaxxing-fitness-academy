<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MembershipTransaction> */
class MembershipTransactionFactory extends Factory
{
    public function definition(): array
    {
        return ['reference_no' => 'TXN-'.Str::ulid(), 'member_id' => Member::factory(), 'membership_category_id' => MembershipCategory::factory(), 'membership_level_id' => MembershipLevel::factory(), 'type' => 'application', 'status' => 'pending', 'category_name' => 'Student', 'level_name' => 'Silver', 'base_fee_cents' => 100000, 'discount_percent' => 10, 'discount_cents' => 10000, 'total_cents' => 90000, 'validity_days' => 30, 'payment_due_at' => now()->addDays(7)];
    }
}
