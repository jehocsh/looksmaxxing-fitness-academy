<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipTransaction;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request, MembershipService $service): View
    {
        $service->expireUnpaid();
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['pending', 'paid', 'approved', 'expired', 'cancelled'])]]);
        $query = MembershipTransaction::query()->with('member')->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        if (filled($filters['q'] ?? null)) {
            $term = $filters['q'];
            $query->where(fn ($query) => $query->where('reference_no', 'like', '%'.$term.'%')->orWhereHas('member', fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')));
        }

        return view('admin.transactions.index', ['transactions' => $query->latest('id')->paginate(15)->withQueryString()]);
    }

    public function show(MembershipTransaction $transaction, MembershipService $service): View
    {
        $service->expireUnpaid($transaction->member_id);
        $transaction->refresh();

        return view('admin.transactions.show', ['transaction' => $transaction->load(['member', 'payments.recorder', 'approver'])]);
    }

    public function renew(Member $member, MembershipService $service): RedirectResponse
    {
        $transaction = $service->createTransaction($member, 'renewal');

        return redirect()->route('transactions.show', $transaction)->with('success', 'Renewal transaction created. Record payment to continue.');
    }

    public function reapply(Member $member, MembershipService $service): RedirectResponse
    {
        $transaction = $service->createTransaction($member, 'application');

        return redirect()->route('transactions.show', $transaction)->with('success', 'New application transaction created.');
    }

    public function approve(Request $request, MembershipTransaction $transaction, MembershipService $service): RedirectResponse
    {
        $service->approve($transaction, $request->user());

        return redirect()->route('transactions.show', $transaction)->with('success', 'Membership approved. The membership period has been updated.');
    }

    public function cancel(MembershipTransaction $transaction, MembershipService $service): RedirectResponse
    {
        $service->cancel($transaction);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction cancelled.');
    }

    public function receipt(MembershipTransaction $transaction, MembershipService $service): View
    {
        $service->expireUnpaid($transaction->member_id);
        $transaction->refresh();

        return view('admin.transactions.receipt', ['transaction' => $transaction->load(['member', 'payments.recorder', 'approver'])]);
    }
}
