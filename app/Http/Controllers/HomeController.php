<?php

namespace App\Http\Controllers;

use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'levels' => MembershipLevel::query()->where('is_active', true)->orderBy('fee_cents')->get(),
            'categories' => MembershipCategory::query()->where('is_active', true)->orderBy('discount_percent')->get(),
        ]);
    }
}
