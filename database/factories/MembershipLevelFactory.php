<?php

namespace Database\Factories;

use App\Models\MembershipLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MembershipLevel> */
class MembershipLevelFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'fee_cents' => 100000, 'validity_days' => 30, 'benefits' => ['Strength and cardio equipment'], 'is_active' => true];
    }
}
