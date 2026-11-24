<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function expireUnpaid(?int $memberId = null): int
    {
        return MembershipTransaction::query()->when($memberId, fn ($query) => $query->where('member_id', $memberId))
            ->where('status', 'pending')->where('payment_due_at', '<=', now())->whereDoesntHave('payments')
            ->update(['status' => 'expired']);
    }

    public static function centavos(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public function createTransaction(Member $member, string $type): MembershipTransaction
    {
        return DB::transaction(function () use ($member, $type): MembershipTransaction {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);
            $this->require(! $member->is_deactivated, 'Inactive members cannot create transactions.');
            $this->require(in_array($type, ['application', 'renewal'], true), 'Invalid transaction type.');
            $this->require($type === 'application' ? $member->activated_at === null : $member->activated_at !== null, 'Choose the correct application or renewal transaction.');
            $this->expireUnpaid($member->id);
            $this->require(! $member->transactions()->whereIn('status', ['pending', 'paid'])->exists(), 'This member already has an open transaction.');
            $category = MembershipCategory::query()->lockForUpdate()->findOrFail($member->membership_category_id);
            $level = MembershipLevel::query()->lockForUpdate()->findOrFail($member->membership_level_id);
            $this->require($category->is_active && $level->is_active, 'Choose an active category and membership level.');
            $this->require($category->discount_percent >= 0 && $category->discount_percent < 100 && $level->validity_days > 0 && $level->fee_cents > 0, 'Membership pricing is invalid.');
            $discount = intdiv($level->fee_cents * $category->discount_percent + 50, 100);

            return MembershipTransaction::query()->create([
                'reference_no' => 'TXN-'.Str::upper((string) Str::ulid()),
                'member_id' => $member->id,
                'membership_category_id' => $category->id,
                'membership_level_id' => $level->id,
                'type' => $type,
                'status' => 'pending',
                'category_name' => $category->name,
                'level_name' => $level->name,
                'base_fee_cents' => $level->fee_cents,
                'discount_percent' => $category->discount_percent,
                'discount_cents' => $discount,
                'total_cents' => $level->fee_cents - $discount,
                'validity_days' => $level->validity_days,
                'payment_due_at' => now()->addDays(7),
            ]);
        }, 3);
    }

    public function recordPayment(MembershipTransaction $transaction, int $amountCents, string $method, User $staff, ?string $reference = null, ?string $submissionToken = null): Payment
    {
        try {
            return DB::transaction(function () use ($transaction, $amountCents, $method, $staff, $reference, $submissionToken): Payment {
                $this->require($staff->is_admin === true, 'An administrator must record the payment.');
                $transaction = MembershipTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
                $reference = filled($reference) ? trim($reference) : null;
                if ($submissionToken !== null && $existing = $transaction->payments()->where('submission_token', $submissionToken)->first()) {
                    $this->require($existing->amount_cents === $amountCents && $existing->method === $method && $existing->external_reference === $reference && $existing->recorded_by === $staff->id, 'This payment form has already been used. Reload the transaction to record another payment.');

                    return $existing;
                }
                $this->require($transaction->status === 'pending', 'Payments require a pending transaction.');
                $this->require($transaction->payment_due_at->isFuture() || $transaction->payments()->exists(), 'The payment deadline has passed. Staff must create a new transaction.');
                $this->require(! $transaction->member->is_deactivated, 'Payments cannot be recorded for inactive members.');
                $this->require(in_array($method, ['cash', 'gcash', 'bank_transfer'], true), 'Choose a supported payment method.');
                $this->require($method === 'cash' || $reference !== null, 'GCash and bank transfers require a payment reference.');
                $this->require($amountCents > 0 && $amountCents <= $transaction->balanceCents(), 'Payment must be positive and cannot exceed the balance.');
                $payment = $transaction->payments()->create([
                    'receipt_no' => 'RCT-'.Str::upper((string) Str::ulid()),
                    'amount_cents' => $amountCents,
                    'method' => $method,
                    'external_reference' => $reference,
                    'paid_at' => now(),
                    'recorded_by' => $staff->id,
                    'submission_token' => $submissionToken,
                ]);
                if ($transaction->balanceCents() === 0) {
                    $transaction->update(['status' => 'paid']);
                }

                return $payment;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['external_reference' => 'This payment reference or form has already been recorded. Check the payment history.']);
        }
    }

    public function approve(MembershipTransaction $transaction, User $staff): MembershipTransaction
    {
        return DB::transaction(function () use ($transaction, $staff): MembershipTransaction {
            $this->require($staff->is_admin === true, 'An administrator must approve membership.');
            $transaction = MembershipTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $this->require($transaction->status === 'paid' && $transaction->balanceCents() === 0, 'Only fully paid transactions may be approved.');
            $member = Member::query()->lockForUpdate()->findOrFail($transaction->member_id);
            $this->require(! $member->is_deactivated, 'Reactivate the member before approval.');
            $start = $transaction->type === 'renewal' && $member->membership_expires_at?->isFuture() ? $member->membership_expires_at : now()->toImmutable();
            $end = $start->addDays($transaction->validity_days);
            $transaction->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $staff->id, 'membership_starts_at' => $start, 'membership_ends_at' => $end]);
            $member->forceFill([
                'activated_at' => $member->activated_at ?? now(),
                'membership_expires_at' => $end,
                'membership_category_id' => $transaction->membership_category_id,
                'membership_level_id' => $transaction->membership_level_id,
            ])->save();

            return $transaction->refresh();
        }, 3);
    }

    public function cancel(MembershipTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $transaction = MembershipTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $this->require($transaction->status === 'pending' && ! $transaction->payments()->exists(), 'Only unpaid pending transactions can be cancelled.');
            $transaction->update(['status' => 'cancelled']);
        }, 3);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['membership' => $message]);
        }
    }
}
