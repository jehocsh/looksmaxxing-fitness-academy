<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'category' => ['nullable', 'integer'], 'level' => ['nullable', 'integer']]);
        $query = Member::query()->with(['category', 'level']);
        if (filled($filters['q'] ?? null)) {
            $q = $filters['q'];
            $query->where(fn ($query) => $query->where('full_name', 'like', '%'.$q.'%')->orWhere('membership_no', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'));
        }
        $query->when($filters['category'] ?? null, fn ($q, $id) => $q->where('membership_category_id', $id))->when($filters['level'] ?? null, fn ($q, $id) => $q->where('membership_level_id', $id));

        return view('admin.members.index', ['members' => $query->latest()->paginate(15)->withQueryString(), 'categories' => MembershipCategory::query()->orderBy('name')->get(), 'levels' => MembershipLevel::query()->orderBy('fee_cents')->get()]);
    }

    public function create(): View
    {
        return $this->form(new Member);
    }

    public function store(StoreMemberRequest $request, MembershipService $service): RedirectResponse
    {
        $member = DB::transaction(function () use ($request, $service): Member {
            $member = Member::query()->create($request->validated());
            $service->createTransaction($member, 'application');

            return $member;
        }, 3);

        return redirect()->route('members.show', $member)->with('success', 'Member registered. The application is pending payment.');
    }

    public function show(Member $member): View
    {
        $member->load(['category', 'level', 'transactions.payments']);

        return view('admin.members.show', compact('member'));
    }

    public function edit(Member $member): View
    {
        return $this->form($member);
    }

    private function form(Member $member): View
    {
        return view('admin.members.form', ['member' => $member, 'categories' => MembershipCategory::query()->where('is_active', true)->orWhere('id', $member->membership_category_id)->orderBy('name')->get(), 'levels' => MembershipLevel::query()->where('is_active', true)->orWhere('id', $member->membership_level_id)->orderBy('fee_cents')->get()]);
    }

    public function update(StoreMemberRequest $request, Member $member): RedirectResponse
    {
        DB::transaction(function () use ($request, $member): void {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);
            $data = $request->validated();
            $changesPlan = (int) $data['membership_category_id'] !== $member->membership_category_id || (int) $data['membership_level_id'] !== $member->membership_level_id;
            if ($changesPlan && $member->transactions()->whereIn('status', ['pending', 'paid'])->exists()) {
                throw ValidationException::withMessages(['membership_level_id' => 'Resolve the open transaction before changing this member’s category or level.']);
            }
            $member->update($data);
        }, 3);

        return redirect()->route('members.show', $member)->with('success', 'Member details updated.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        $member->forceFill(['is_deactivated' => true])->save();

        return redirect()->route('members.show', $member)->with('success', 'Member deactivated. Transaction history is preserved.');
    }

    public function reactivate(Member $member): RedirectResponse
    {
        $member->forceFill(['is_deactivated' => false])->save();

        return redirect()->route('members.show', $member)->with('success', 'Member reactivated.');
    }
}
