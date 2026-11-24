<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Notifications\MembershipApplicationSubmitted;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function create(): View
    {
        return view('membership.apply', ['categories' => MembershipCategory::query()->where('is_active', true)->orderBy('name')->get(), 'levels' => MembershipLevel::query()->where('is_active', true)->orderBy('fee_cents')->get()]);
    }

    public function store(StoreApplicationRequest $request, MembershipService $service): RedirectResponse
    {
        $transaction = DB::transaction(function () use ($request, $service) {
            $data = $request->safe()->except('consent');
            $member = Member::query()->create($data);
            $transaction = $service->createTransaction($member, 'application');
            Notification::route('mail', $member->email)->notify(new MembershipApplicationSubmitted($transaction->id));

            return $transaction;
        }, 3);

        return redirect()->route('membership.create')->with('application_reference', $transaction->reference_no)->with('application_total', $transaction->total_cents);
    }
}
