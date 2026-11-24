<?php

namespace Tests\Feature;

use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_public_application_is_committed_then_processed_by_database_email_worker(): void
    {
        config(['queue.default' => 'database', 'services.brevo.key' => 'test-brevo-key', 'services.brevo.sender_email' => 'sender@example.test']);
        $category = MembershipCategory::factory()->create();
        $level = MembershipLevel::factory()->create();
        Http::preventStrayRequests();
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'queued-email-test'], 201)]);

        $this->post(route('membership.store'), ['full_name' => 'Queued Applicant', 'email' => 'queued@example.test', 'membership_category_id' => $category->id, 'membership_level_id' => $level->id, 'consent' => '1'])->assertRedirect(route('membership.create'))->assertSessionHas('application_reference');

        $this->assertDatabaseCount('jobs', 1);
        Http::assertNothingSent();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1, '--no-interaction' => true])->assertSuccessful();
        Http::assertSent(fn ($request) => $request['to'][0]['email'] === 'queued@example.test');
        $this->assertNotNull(MembershipTransaction::query()->sole()->notification_sent_at);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_email_status_requires_administrator_without_contacting_brevo(): void
    {
        Http::preventStrayRequests();

        $this->get(route('email-status'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('email-status'))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_email_status_reports_missing_configuration_without_contacting_brevo(): void
    {
        config(['services.brevo.key' => null, 'services.brevo.sender_email' => null]);
        Http::preventStrayRequests();

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('email-status'))->assertOk()->assertSee('Missing: add BREVO_API_KEY')->assertSee('Missing: add BREVO_SENDER_EMAIL');

        Http::assertNothingSent();
    }

    public function test_email_status_checks_connection_and_hides_credentials_and_raw_failed_job_data(): void
    {
        config(['services.brevo.key' => 'private-key-value', 'services.brevo.sender_email' => 'sender@example.test']);
        Http::preventStrayRequests();
        Http::fake(['https://api.brevo.com/v3/smtp/statistics/aggregatedReport*' => Http::response(['requests' => 0], 200)]);
        DB::table('failed_jobs')->insert(['uuid' => 'test-failure', 'connection' => 'database', 'queue' => 'default', 'payload' => 'private-key-value', 'exception' => 'HTTP request returned status code 401: private-key-value', 'failed_at' => now()]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('email-status'))->assertOk()->assertSee('API connection accepted')->assertSee('Brevo rejected the API key')->assertDontSee('private-key-value');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_email_status_identifies_provider_ip_block_without_rendering_raw_response(): void
    {
        config(['services.brevo.key' => 'private-key-value']);
        Http::preventStrayRequests();
        Http::fake(['https://api.brevo.com/v3/smtp/statistics/aggregatedReport*' => Http::response(['message' => 'Unrecognised IP address: private-key-value'], 401)]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('email-status'))->assertOk()->assertSee('Server IP needs authorization in Brevo')->assertDontSee('private-key-value');

        Http::assertSentCount(1);
    }

    public static function providerRejections(): array
    {
        return [
            'invalid key' => [401, 'Brevo rejected the API key'],
            'permissions' => [403, 'Brevo denied access: check API permissions and authorized IPs'],
            'sender or account' => [400, 'Brevo rejected the request: check sender verification and account activation'],
            'rate limit' => [429, 'Brevo sending or request limit reached'],
            'provider failure' => [500, 'Email job failed; inspect the hosting logs privately'],
        ];
    }

    #[DataProvider('providerRejections')]
    public function test_email_status_reports_safe_reason_for_rejected_connection(int $status, string $message): void
    {
        config(['services.brevo.key' => 'private-key-value']);
        Http::preventStrayRequests();
        Http::fake(['https://api.brevo.com/v3/smtp/statistics/aggregatedReport*' => Http::response(['message' => 'private-key-value'], $status)]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('email-status'))->assertOk()->assertSee($message)->assertDontSee('private-key-value');

        Http::assertSentCount(1);
    }

    public function test_email_status_reports_connection_failure_without_exposing_exception(): void
    {
        config(['services.brevo.key' => 'private-key-value']);
        Http::preventStrayRequests();
        Http::fake(['https://api.brevo.com/v3/smtp/statistics/aggregatedReport*' => fn () => throw new ConnectionException('private-key-value')]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('email-status'))->assertOk()->assertSee('Cannot connect to Brevo')->assertDontSee('private-key-value');
    }

    public function test_admin_can_requeue_failed_application_confirmation_once_and_worker_can_send_it(): void
    {
        $this->freezeTime();
        config(['queue.default' => 'database', 'services.brevo.key' => 'test-brevo-key', 'services.brevo.sender_email' => 'sender@example.test']);
        $category = MembershipCategory::factory()->create();
        $level = MembershipLevel::factory()->create();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.brevo.com/v3/smtp/statistics/aggregatedReport*' => Http::response(['requests' => 0], 200),
            'https://api.brevo.com/v3/smtp/email' => Http::sequence()
                ->push(['code' => 'out_of_range', 'message' => 'Idempotency key exceeds char limit'], 400)
                ->push(['code' => 'out_of_range', 'message' => 'Idempotency key exceeds char limit'], 400)
                ->push(['code' => 'out_of_range', 'message' => 'Idempotency key exceeds char limit'], 400)
                ->push(['messageId' => 'retry-success'], 201),
        ]);
        $this->post(route('membership.store'), ['full_name' => 'Retry Applicant', 'email' => 'retry@example.test', 'membership_category_id' => $category->id, 'membership_level_id' => $level->id, 'consent' => '1'])->assertSessionHas('application_reference');
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--no-interaction' => true])->assertSuccessful();
            $this->travel(61)->seconds();
        }
        $this->assertDatabaseCount('failed_jobs', 1);
        $uuid = DB::table('failed_jobs')->value('uuid');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('email-status'))->assertOk()->assertSee('Retry confirmation')->assertSee('Email request format error; deploy the latest update and retry');

        $this->post(route('email-status.retry', $uuid))->assertRedirect(route('email-status'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->post(route('email-status.retry', $uuid))->assertNotFound();
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertNotNull(MembershipTransaction::query()->sole()->notification_sent_at);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_email_retry_requires_administrator(): void
    {
        $uuid = (string) Str::uuid();

        $this->post(route('email-status.retry', $uuid))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('email-status.retry', $uuid))->assertForbidden();

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_email_retry_rejects_unrelated_failed_jobs(): void
    {
        config(['services.brevo.key' => 'test-brevo-key', 'services.brevo.sender_email' => 'sender@example.test']);
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'OtherJob']), 'exception' => 'Unrelated failure', 'failed_at' => now()]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->post(route('email-status.retry', $uuid))->assertUnprocessable();

        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_email_retry_requires_configured_provider(): void
    {
        config(['services.brevo.key' => null, 'services.brevo.sender_email' => null]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->post(route('email-status.retry', (string) Str::uuid()))->assertSessionHasErrors('email');

        $this->assertDatabaseCount('jobs', 0);
    }
}
