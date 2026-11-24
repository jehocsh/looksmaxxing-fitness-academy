<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\MembershipTransaction;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, MembershipTransaction $transaction, MembershipService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->recordPayment($transaction, MembershipService::centavos((string) $data['amount']), $data['method'], $request->user(), $data['external_reference'] ?? null, $data['submission_token']);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Payment recorded with an official receipt number.');
    }
}
