<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'totalMembers' => Member::query()->count(),
            'activeMembers' => Member::query()->where('is_deactivated', false)->where('membership_expires_at', '>', now())->count(),
            'pendingTransactions' => MembershipTransaction::query()->where('status', 'pending')->count(),
            'collectedCents' => (int) Payment::query()->sum('amount_cents'),
            'transactions' => MembershipTransaction::query()->with('member')->latest()->limit(8)->get(),
            'levels' => MembershipLevel::query()->orderBy('fee_cents')->get(),
        ]);
    }
}
