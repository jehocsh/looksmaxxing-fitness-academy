<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\User;
use App\Notifications\MembershipApplicationSubmitted;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipScreensTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_application_creates_discounted_pending_transaction_and_acknowledgment(): void
    {
        Notification::fake([MembershipApplicationSubmitted::class]);
        $category = MembershipCategory::factory()->create(['discount_percent' => 10]);
        $level = MembershipLevel::factory()->create(['fee_cents' => 100000]);
        $payload = ['full_name' => 'Jordan Applicant', 'email' => 'JORDAN@example.test', 'membership_category_id' => $category->id, 'membership_level_id' => $level->id, 'consent' => '1'];

        $this->post(route('membership.store'), $payload)->assertRedirect(route('membership.create'))->assertSessionHas('application_total', 90000)->assertSessionHas('application_reference');

        $member = Member::query()->sole();
        $transaction = MembershipTransaction::query()->sole();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'email' => 'jordan@example.test', 'activated_at' => null, 'latitude' => null]);
        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'member_id' => $member->id, 'status' => 'pending', 'total_cents' => 90000]);
        Notification::assertSentOnDemand(MembershipApplicationSubmitted::class, fn ($notification, $channels, $notifiable) => $notification->transactionId === $transaction->id && $notifiable->routes['mail'] === 'jordan@example.test');
    }

    public function test_invalid_application_rejects_inactive_plan_missing_consent_and_privileged_fields(): void
    {
        Notification::fake([MembershipApplicationSubmitted::class]);
        $category = MembershipCategory::factory()->create();
        $level = MembershipLevel::factory()->create(['is_active' => false]);

        $this->post(route('membership.store'), ['full_name' => 'Invalid Applicant', 'email' => 'applicant@example.test', 'membership_category_id' => $category->id, 'membership_level_id' => $level->id, 'membership_expires_at' => '2030-01-01', 'latitude' => 999])
            ->assertSessionHasErrors(['membership_level_id', 'consent', 'membership_expires_at', 'latitude', 'longitude']);

        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('membership_transactions', 0);
        Notification::assertNothingSent();
    }

    public function test_duplicate_member_email_is_rejected_without_another_application(): void
    {
        $member = Member::factory()->create();

        $this->post(route('membership.store'), [...$this->memberData($member), 'consent' => '1'])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_admin_registers_searches_updates_and_deactivates_member_without_losing_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = MembershipCategory::factory()->create();
        $level = MembershipLevel::factory()->create();
        $payload = ['full_name' => 'Alex Member', 'email' => 'alex@example.test', 'membership_category_id' => $category->id, 'membership_level_id' => $level->id];

        $response = $this->actingAs($admin)->post(route('members.store'), $payload);

        $member = Member::query()->sole();
        $response->assertRedirect(route('members.show', $member));
        $this->get(route('members.show', $member))->assertOk()->assertSee($member->membership_no)->assertSee('TRANSACTION HISTORY');
        $this->get(route('members.edit', $member))->assertOk()->assertSee('Alex Member');
        $this->get(route('members.index', ['q' => 'Alex', 'category' => $category->id, 'level' => $level->id]))->assertOk()->assertSee('Alex Member');
        $this->get(route('members.index', ['q' => 'Unmatched person']))->assertOk()->assertDontSee('alex@example.test');
        $this->put(route('members.update', $member), [...$payload, 'phone' => '09171234567'])->assertRedirect(route('members.show', $member));
        $this->assertDatabaseHas('members', ['id' => $member->id, 'phone' => '09171234567']);
        $this->delete(route('members.destroy', $member))->assertRedirect(route('members.show', $member));
        $this->assertDatabaseHas('members', ['id' => $member->id, 'is_deactivated' => true]);
        $this->assertDatabaseCount('membership_transactions', 1);
        $this->post(route('members.reactivate', $member))->assertRedirect(route('members.show', $member));
        $this->assertDatabaseHas('members', ['id' => $member->id, 'is_deactivated' => false]);
    }

    public function test_member_details_remain_editable_when_their_existing_plan_is_inactive(): void
    {
        $member = Member::factory()->create();
        $member->category->update(['is_active' => false]);
        $member->level->update(['is_active' => false]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->put(route('members.update', $member), [...$this->memberData($member), 'phone' => '09991234567'])
            ->assertRedirect(route('members.show', $member))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('members', ['id' => $member->id, 'phone' => '09991234567']);
    }

    public function test_open_transaction_prevents_changing_plan_and_keeps_original_price(): void
    {
        $member = Member::factory()->create();
        $transaction = app(MembershipService::class)->createTransaction($member, 'application');
        $other = MembershipLevel::factory()->create();

        $this->actingAs(User::factory()->create(['is_admin' => true]))->put(route('members.update', $member), [...$this->memberData($member), 'membership_level_id' => $other->id])->assertSessionHasErrors('membership_level_id');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'membership_level_id' => $member->membership_level_id]);
        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'total_cents' => 90000]);
    }

    public function test_cash_form_resubmission_records_only_one_payment_and_approval_requires_full_payment(): void
    {
        $this->freezeTime();
        $transaction = app(MembershipService::class)->createTransaction(Member::factory()->create(), 'application');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $partial = ['amount' => '300.00', 'method' => 'cash', 'submission_token' => (string) Str::uuid()];

        $this->post(route('payments.store', $transaction), $partial)->assertRedirect(route('transactions.show', $transaction));
        $this->post(route('payments.store', $transaction), $partial)->assertRedirect(route('transactions.show', $transaction))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(60000, $transaction->balanceCents());
        $this->post(route('transactions.approve', $transaction))->assertSessionHasErrors('membership');
        $this->post(route('payments.store', $transaction), ['amount' => '600.00', 'method' => 'bank_transfer', 'external_reference' => 'BANK-FINAL-1', 'submission_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'status' => 'paid']);
        $this->post(route('transactions.approve', $transaction))->assertRedirect(route('transactions.show', $transaction));
        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'status' => 'approved', 'approved_by' => $admin->id]);
        $this->get(route('transactions.receipt', $transaction))->assertOk()->assertSee('BANK-FINAL-1')->assertSee('Membership period');
        $this->post(route('members.renew', $transaction->member))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_transactions', ['member_id' => $transaction->member_id, 'type' => 'renewal', 'status' => 'pending']);
    }

    public function test_duplicate_transfer_reference_returns_validation_error_without_second_payment(): void
    {
        $service = app(MembershipService::class);
        $transaction = $service->createTransaction(Member::factory()->create(), 'application');
        $admin = User::factory()->create(['is_admin' => true]);
        $service->recordPayment($transaction, 10000, 'gcash', $admin, 'GCASH-UNIQUE-1');

        $this->actingAs($admin)->post(route('payments.store', $transaction), ['amount' => '100', 'method' => 'gcash', 'external_reference' => 'GCASH-UNIQUE-1', 'submission_token' => (string) Str::uuid()])->assertSessionHasErrors('external_reference');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(80000, $transaction->balanceCents());
    }

    public function test_reusing_payment_form_with_different_amount_is_rejected(): void
    {
        $transaction = app(MembershipService::class)->createTransaction(Member::factory()->create(), 'application');
        $token = (string) Str::uuid();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post(route('payments.store', $transaction), ['amount' => '100', 'method' => 'cash', 'submission_token' => $token])->assertSessionHasNoErrors();

        $this->post(route('payments.store', $transaction), ['amount' => '200', 'method' => 'cash', 'submission_token' => $token])->assertSessionHasErrors('membership');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(80000, $transaction->balanceCents());
    }

    public function test_member_pagination_preserves_search_and_category_filters(): void
    {
        $category = MembershipCategory::factory()->create();
        $level = MembershipLevel::factory()->create();
        Member::factory()->count(16)->sequence(fn ($sequence) => ['full_name' => 'Pagination '.$sequence->index])->create(['membership_category_id' => $category->id, 'membership_level_id' => $level->id]);
        $filters = ['q' => 'Pagination', 'category' => $category->id];
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('members.index', $filters))->assertOk()->assertSee('Page 1 of 2')->assertSee('page=2')->assertSee('q=Pagination');
        $this->get(route('members.index', [...$filters, 'page' => 2]))->assertOk()->assertSee('Page 2 of 2')->assertSee('Pagination 0')->assertDontSee('Pagination 15');
    }

    public static function invalidAmounts(): array
    {
        return ['negative' => ['-1'], 'excess precision' => ['2.345'], 'exponent' => ['1e2'], 'too large' => ['1000000']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_payment_amount_format_records_no_payment(string $amount): void
    {
        $transaction = MembershipTransaction::factory()->create();

        $this->actingAs(User::factory()->create(['is_admin' => true]))->post(route('payments.store', $transaction), ['amount' => $amount, 'method' => 'cash', 'submission_token' => (string) Str::uuid()])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_expired_detail_and_profile_allow_reapplication_without_payment_form(): void
    {
        $this->freezeTime();
        $transaction = MembershipTransaction::factory()->create(['payment_due_at' => now()->subDay()]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('transactions.show', $transaction))->assertOk()->assertSee('Expired')->assertDontSee('Record payment ↗');
        $this->get(route('members.show', $transaction->member))->assertOk()->assertSee('Create application');
        $this->post(route('members.reapply', $transaction->member))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('membership_transactions', ['id' => $transaction->id, 'status' => 'expired']);
        $this->assertDatabaseCount('membership_transactions', 2);
    }

    public function test_admin_can_manage_unused_catalog_entries_but_cannot_delete_used_entries(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post(route('categories.store'), ['name' => 'Community', 'discount_percent' => 5, 'is_active' => 1])->assertRedirect(route('categories.index'));
        $category = MembershipCategory::query()->sole();
        $this->put(route('categories.update', $category), ['name' => 'Community', 'discount_percent' => 8, 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_categories', ['id' => $category->id, 'discount_percent' => 8, 'is_active' => false]);
        $this->delete(route('categories.destroy', $category))->assertSessionHasNoErrors();
        $this->assertModelMissing($category);
        $this->post(route('levels.store'), ['name' => 'Weekend', 'fee' => '123.45', 'validity_days' => 7, 'benefits_text' => "Strength equipment\nCardio", 'is_active' => 1])->assertRedirect(route('levels.index'));
        $level = MembershipLevel::query()->sole();
        $this->assertDatabaseHas('membership_levels', ['id' => $level->id, 'fee_cents' => 12345]);
        $this->put(route('levels.update', $level), ['name' => 'Weekend', 'fee' => '150.50', 'validity_days' => 14, 'benefits_text' => 'All equipment', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_levels', ['id' => $level->id, 'fee_cents' => 15050, 'validity_days' => 14]);
        $this->delete(route('levels.destroy', $level))->assertSessionHasNoErrors();
        $this->assertModelMissing($level);
        $member = Member::factory()->create();
        $this->delete(route('categories.destroy', $member->category))->assertSessionHasErrors('category');
        $this->delete(route('levels.destroy', $member->level))->assertSessionHasErrors('level');
        $this->assertModelExists($member->category);
        $this->assertModelExists($member->level);
    }

    public function test_member_name_is_escaped_in_profile_and_printable_record(): void
    {
        $transaction = app(MembershipService::class)->createTransaction(Member::factory()->create(['full_name' => '<script>alert(1)</script>']), 'application');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('members.show', $transaction->member))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('transactions.receipt', $transaction))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public static function staffScreens(): array
    {
        return ['members' => ['members.index'], 'new member' => ['members.create'], 'categories' => ['categories.index'], 'levels' => ['levels.index'], 'transactions' => ['transactions.index']];
    }

    #[DataProvider('staffScreens')]
    public function test_staff_screens_require_admin_and_render_for_admin(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route($route))->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route($route))->assertOk()->assertSee('ACADEMY CONTROL');
    }

    public function test_non_admin_cannot_register_members_or_record_payments(): void
    {
        $transaction = MembershipTransaction::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('members.store'), [])->assertForbidden();
        $this->post(route('payments.store', $transaction), [])->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('members', 1);
    }

    private function memberData(Member $member): array
    {
        return $member->only(['full_name', 'email', 'phone', 'address', 'membership_category_id', 'membership_level_id']);
    }
}
