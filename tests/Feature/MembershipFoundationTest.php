<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipFoundationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_home_displays_the_seeded_plans_and_discounts(): void
    {
        $this->seed();

        $this->get(route('home'))->assertOk()->assertSee('LOOKSMAXXING')->assertSee('700')->assertSee('2,500')->assertSee('Senior Citizen')->assertSee('20% off');
    }

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_admin_can_sign_in_view_dashboard_and_sign_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'a-test-password']);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'a-test-password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertOk()->assertSee('LATEST TRANSACTIONS');
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_rejects_a_valid_password_for_a_non_admin(): void
    {
        $user = User::factory()->create(['password' => 'a-test-password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'a-test-password'])->assertSessionHasErrors(['email' => 'These credentials do not match an administrator account.']);
        $this->assertGuest();
    }

    public function test_login_rejects_wrong_password_and_unexpected_admin_flag(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong', 'is_admin' => true])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->post(route('login.store'), [])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_repeated_bad_logins_are_rate_limited(): void
    {
        $email = 'rate-limit@example.test';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), ['email' => $email, 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_rendered_plan_benefits_are_escaped(): void
    {
        MembershipLevel::factory()->create(['benefits' => ['<script>alert(1)</script>']]);

        $this->get(route('home'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_student_silver_quote_is_900_pesos_and_keeps_historical_pricing(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();

        $transaction = app(MembershipService::class)->createTransaction($member, 'application');
        $member->level->update(['fee_cents' => 200000]);
        $member->category->update(['discount_percent' => 20]);

        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'base_fee_cents' => 100000, 'discount_cents' => 10000, 'total_cents' => 90000, 'discount_percent' => 10, 'status' => 'pending']);
        $this->assertStringStartsWith('LMX-', $member->membership_no);
        $this->assertStringStartsWith('TXN-', $transaction->reference_no);
        $this->assertNull($member->refresh()->activated_at);
    }

    public function test_duplicate_open_transactions_are_rejected(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();
        app(MembershipService::class)->createTransaction($member, 'application');

        try {
            app(MembershipService::class)->createTransaction($member, 'application');
            $this->fail('Duplicate transaction was allowed.');
        } catch (ValidationException $exception) {
            $this->assertSame('This member already has an open transaction.', $exception->errors()['membership'][0]);
        }
        $this->assertDatabaseCount('membership_transactions', 1);
    }

    public function test_partial_payment_cannot_activate_membership(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(MembershipService::class);
        $transaction = $service->createTransaction($member, 'application');
        $service->recordPayment($transaction, 30000, 'cash', $admin);

        try {
            $service->approve($transaction, $admin);
            $this->fail('An incomplete payment was approved.');
        } catch (ValidationException $exception) {
            $this->assertSame('Only fully paid transactions may be approved.', $exception->errors()['membership'][0]);
        }
        $this->assertSame(60000, $transaction->balanceCents());
        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'status' => 'pending', 'approved_at' => null]);
        $this->assertNull($member->refresh()->membership_expires_at);
    }

    public function test_full_payment_approval_activates_thirty_days_and_cannot_be_repeated(): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00'));
        $member = Member::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(MembershipService::class);
        $transaction = $service->createTransaction($member, 'application');
        $service->recordPayment($transaction, 90000, 'gcash', $admin, 'GCASH-APPROVAL-TEST');
        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'status' => 'paid']);
        $this->assertNull($member->refresh()->activated_at);

        $approved = $service->approve($transaction, $admin);

        $this->assertSame('approved', $approved->status);
        $this->assertSame('2026-12-24 12:00:00', $member->refresh()->membership_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('Active', $member->membershipStatus());
        try {
            $service->approve($transaction, $admin);
            $this->fail('A transaction was approved twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('membership', $exception->errors());
        }
        $this->assertSame('2026-12-24 12:00:00', $member->refresh()->membership_expires_at->format('Y-m-d H:i:s'));
    }

    public static function renewalDates(): array
    {
        return [
            'active membership extends from existing expiry' => ['2026-12-24 12:00:00', '2027-01-23 12:00:00'],
            'expired membership restarts on approval' => ['2026-11-20 12:00:00', '2026-12-24 12:00:00'],
        ];
    }

    #[DataProvider('renewalDates')]
    public function test_renewal_preserves_or_restarts_the_correct_period(string $existingEnd, string $expectedEnd): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00'));
        $member = Member::factory()->create(['activated_at' => '2026-10-20 12:00:00', 'membership_expires_at' => $existingEnd]);
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(MembershipService::class);
        $transaction = $service->createTransaction($member, 'renewal');
        $service->recordPayment($transaction, 90000, 'bank_transfer', $admin, 'BANK-RENEWAL-TEST');

        $service->approve($transaction, $admin);

        $this->assertSame($expectedEnd, $member->refresh()->membership_expires_at->format('Y-m-d H:i:s'));
    }

    public static function invalidPayments(): array
    {
        return ['zero' => [0, 'cash', null], 'negative' => [-1, 'cash', null], 'overpayment' => [90001, 'cash', null], 'unsupported method' => [90000, 'card', null], 'missing transfer reference' => [90000, 'bank_transfer', null]];
    }

    #[DataProvider('invalidPayments')]
    public function test_invalid_payments_leave_the_transaction_unchanged(int $amount, string $method, ?string $reference): void
    {
        $this->freezeTime();
        $transaction = app(MembershipService::class)->createTransaction(Member::factory()->create(), 'application');
        $admin = User::factory()->create(['is_admin' => true]);

        try {
            app(MembershipService::class)->recordPayment($transaction, $amount, $method, $admin, $reference);
            $this->fail('An invalid payment was recorded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('membership', $exception->errors());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'status' => 'pending']);
    }

    public function test_paid_funds_prevent_cancellation(): void
    {
        $this->freezeTime();
        $service = app(MembershipService::class);
        $transaction = $service->createTransaction(Member::factory()->create(), 'application');
        $service->recordPayment($transaction, 10000, 'cash', User::factory()->create(['is_admin' => true]));

        try {
            $service->cancel($transaction);
            $this->fail('A transaction with recorded funds was cancelled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('membership', $exception->errors());
        }
        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'status' => 'pending']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_unpaid_transaction_can_be_cancelled_and_replaced(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();
        $service = app(MembershipService::class);
        $transaction = $service->createTransaction($member, 'application');

        $service->cancel($transaction);
        $replacement = $service->createTransaction($member, 'application');

        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'status' => 'cancelled']);
        $this->assertNotSame($transaction->reference_no, $replacement->reference_no);
    }

    public function test_expiry_keeps_partially_paid_transactions_open(): void
    {
        $this->freezeTime();
        $unpaid = MembershipTransaction::factory()->create(['payment_due_at' => now()->subDay()]);
        $partial = MembershipTransaction::factory()->create(['payment_due_at' => now()->subDay()]);
        Payment::factory()->create(['membership_transaction_id' => $partial->id, 'amount_cents' => 10000]);

        $this->artisan('looksmaxxing:expire')->assertSuccessful();

        $this->assertDatabaseHas('membership_transactions', ['id' => $unpaid->id, 'status' => 'expired']);
        $this->assertDatabaseHas('membership_transactions', ['id' => $partial->id, 'status' => 'pending']);
    }

    public function test_inactive_member_cannot_create_transaction(): void
    {
        $member = Member::factory()->create(['is_deactivated' => true]);

        try {
            app(MembershipService::class)->createTransaction($member, 'application');
            $this->fail('An inactive member created a transaction.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('membership', $exception->errors());
        }
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_non_admin_cannot_record_payment(): void
    {
        $this->freezeTime();
        $transaction = app(MembershipService::class)->createTransaction(Member::factory()->create(), 'application');
        $staff = User::factory()->create();

        try {
            app(MembershipService::class)->recordPayment($transaction, 90000, 'cash', $staff);
            $this->fail('An unprivileged account recorded a payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('membership', $exception->errors());
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_reseeding_preserves_administrator_price_changes(): void
    {
        $this->seed();
        MembershipLevel::query()->where('name', 'Silver')->update(['fee_cents' => 120000]);

        $this->seed();

        $this->assertDatabaseCount('membership_levels', 4);
        $this->assertDatabaseCount('membership_categories', 4);
        $this->assertDatabaseHas('membership_levels', ['name' => 'Silver', 'fee_cents' => 120000]);
    }
}
