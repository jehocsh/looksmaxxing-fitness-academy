<?php

namespace Tests\Feature;

use App\Models\MembershipTransaction;
use App\Notifications\Channels\BrevoChannel;
use App\Notifications\MembershipApplicationSubmitted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_weather_returns_only_display_fields_and_reuses_cached_response(): void
    {
        config(['services.openweather.key' => ' test-weather-key ']);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.openweathermap.org/data/2.5/weather*' => Http::response(['name' => 'Manila', 'main' => ['temp' => 30.5, 'humidity' => 70], 'weather' => [['description' => 'scattered clouds']], 'private' => 'not-exposed'])]);

        $this->getJson(route('weather.show', ['lat' => 14.619, 'lon' => 120.966]))->assertOk()->assertExactJson(['place' => 'Manila', 'temperature' => 30.5, 'description' => 'scattered clouds', 'humidity' => 70]);
        $this->getJson(route('weather.show', ['lat' => 14.619, 'lon' => 120.966]))->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['appid'] === 'test-weather-key' && $request['units'] === 'metric');
    }

    public function test_invalid_coordinates_return_422_without_contacting_weather_provider(): void
    {
        Http::preventStrayRequests();

        $this->getJson(route('weather.show', ['lat' => 91, 'lon' => -181]))->assertUnprocessable()->assertJsonValidationErrors(['lat', 'lon']);

        Http::assertNothingSent();
    }

    public function test_missing_weather_key_returns_503_without_contacting_provider(): void
    {
        config(['services.openweather.key' => null]);
        Http::preventStrayRequests();

        $this->getJson(route('weather.show', ['lat' => 14.6, 'lon' => 120.9]))->assertServiceUnavailable()->assertJsonPath('message', 'Weather is currently unavailable.')->assertJsonPath('code', 'weather_not_configured');

        Http::assertNothingSent();
    }

    public function test_weather_provider_failure_returns_503_without_exposing_key(): void
    {
        config(['services.openweather.key' => 'secret-weather-key']);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.openweathermap.org/data/2.5/weather*' => Http::response(['message' => 'secret-weather-key'], 401)]);
        Log::spy();

        $this->getJson(route('weather.show', ['lat' => 14.6, 'lon' => 120.9]))->assertServiceUnavailable()->assertJsonPath('code', 'weather_authentication_failed')->assertDontSee('secret-weather-key');

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Weather request failed.', ['code' => 'weather_authentication_failed', 'provider_status' => 401]);
    }

    public static function weatherProviderFailures(): array
    {
        return ['rate limit' => [429, 'weather_rate_limited'], 'provider failure' => [500, 'weather_provider_error']];
    }

    #[DataProvider('weatherProviderFailures')]
    public function test_weather_reports_safe_provider_failure_code(int $status, string $code): void
    {
        config(['services.openweather.key' => 'secret-weather-key']);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.openweathermap.org/data/2.5/weather*' => Http::response(['message' => 'secret-weather-key'], $status)]);

        $this->getJson(route('weather.show', ['lat' => 14.6, 'lon' => 120.9]))->assertServiceUnavailable()->assertJsonPath('code', $code)->assertDontSee('secret-weather-key');

        Http::assertSentCount(1);
    }

    public function test_weather_connection_failure_does_not_expose_request_url_or_key(): void
    {
        config(['services.openweather.key' => 'secret-weather-key']);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.openweathermap.org/data/2.5/weather*' => fn () => throw new ConnectionException('https://api.openweathermap.org/?appid=secret-weather-key')]);
        Log::spy();

        $this->getJson(route('weather.show', ['lat' => 14.6, 'lon' => 120.9]))->assertServiceUnavailable()->assertJsonPath('code', 'weather_connection_failed')->assertDontSee('secret-weather-key');

        Log::shouldHaveReceived('warning')->once()->with('Weather request failed.', ['code' => 'weather_connection_failed', 'provider_status' => null]);
    }

    public function test_malformed_weather_response_returns_safe_error_instead_of_displaying_invalid_data(): void
    {
        config(['services.openweather.key' => 'secret-weather-key']);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.openweathermap.org/data/2.5/weather*' => Http::response(['name' => 'Manila'])]);

        $this->getJson(route('weather.show', ['lat' => 14.6, 'lon' => 120.9]))->assertServiceUnavailable()->assertJsonPath('code', 'weather_invalid_response');

        Http::assertSentCount(1);
    }

    public function test_brevo_sends_escaped_application_email_and_does_not_resend_delivered_notification(): void
    {
        $this->freezeTime();
        config(['services.brevo.key' => 'test-brevo-key', 'services.brevo.sender_email' => 'sender@example.test']);
        $transaction = MembershipTransaction::factory()->create();
        $transaction->member->update(['full_name' => '<script>alert(1)</script>']);
        Http::preventStrayRequests();
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-message'], 201)]);
        $notifiable = (new AnonymousNotifiable)->route('mail', $transaction->member->email);
        $notification = new MembershipApplicationSubmitted($transaction->id);

        app(BrevoChannel::class)->send($notifiable, $notification);
        app(BrevoChannel::class)->send($notifiable, $notification);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-brevo-key') && $request['headers']['idempotencyKey'] === 'membership-'.$transaction->reference_no && $request['to'][0]['email'] === $transaction->member->email && str_contains($request['htmlContent'], '&lt;script&gt;') && ! str_contains($request['htmlContent'], '<script>alert(1)</script>'));
        $this->assertNotNull($transaction->refresh()->notification_sent_at);
    }

    public function test_application_page_keeps_server_api_keys_private_and_allows_optional_location(): void
    {
        config(['services.brevo.key' => 'private-brevo-key', 'services.openweather.key' => 'private-weather-key']);

        $this->get(route('membership.create'))->assertOk()->assertSee('Submit application')->assertSee('Clear location')->assertDontSee('private-brevo-key')->assertDontSee('private-weather-key');
    }
}
