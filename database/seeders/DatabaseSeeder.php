<?php

namespace Database\Seeders;

use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Regular' => 0, 'Student' => 10, 'Senior Citizen' => 20, 'Corporate' => 15] as $name => $discount) {
            MembershipCategory::query()->firstOrCreate(['name' => $name], ['discount_percent' => $discount, 'is_active' => true]);
        }
        $plans = [
            ['Basic', 70000, ['Strength training equipment', '30 days of gym access']],
            ['Silver', 100000, ['Strength and cardio equipment', '30 days of gym access']],
            ['Gold', 150000, ['All strength and cardio equipment', 'Group fitness classes', '30 days of gym access']],
            ['Platinum', 250000, ['All Gold benefits', 'Four coaching sessions', '30 days of gym access']],
        ];
        foreach ($plans as [$name, $fee, $benefits]) {
            MembershipLevel::query()->firstOrCreate(['name' => $name], ['fee_cents' => $fee, 'validity_days' => 30, 'benefits' => $benefits, 'is_active' => true]);
        }
    }
}
