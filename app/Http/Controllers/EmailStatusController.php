<?php

namespace App\Http\Controllers;

use App\Models\MembershipTransaction;
use App\Notifications\MembershipApplicationSubmitted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailStatusController extends Controller
{
    public function __invoke(): View
    {
        $key = trim((string) config('services.brevo.key'));
        $sender = trim((string) config('services.brevo.sender_email'));
        $provider = 'Key not configured';
        if ($key !== '') {
            try {
                $response = Http::acceptJson()->withHeaders(['api-key' => $key])->connectTimeout(3)->timeout(6)
                    ->get('https://api.brevo.com/v3/smtp/statistics/aggregatedReport', ['days' => 1]);
                $message = $response->json('message');
                $provider = $response->successful() ? 'API connection accepted' : $this->failureReason('HTTP request returned status code '.$response->status().' '.(is_string($message) ? $message : ''));
            } catch (ConnectionException $exception) {
                $provider = 'Cannot connect to Brevo';
            }
        }
        $queueDatabase = DB::connection(config('queue.connections.database.connection'));
        $latestFailure = $queueDatabase->table('failed_jobs')->orderByDesc('id')->value('exception');

        return view('admin.email-status', [
            'keyConfigured' => $key !== '',
            'senderConfigured' => $sender !== '',
            'queueConnection' => config('queue.default'),
            'provider' => $provider,
            'queuedJobs' => $queueDatabase->table('jobs')->count(),
            'failedJobs' => $queueDatabase->table('failed_jobs')->count(),
            'lastFailure' => $latestFailure === null ? 'None recorded' : $this->failureReason($latestFailure),
            'lastAccepted' => MembershipTransaction::query()->max('notification_sent_at'),
            'retryableEmails' => $queueDatabase->table('failed_jobs')->orderByDesc('id')->limit(50)->get(['uuid', 'failed_at', 'connection', 'payload'])
                ->filter(fn (object $job): bool => $this->isApplicationEmailFailure($job))
                ->map(fn (object $job): array => ['uuid' => $job->uuid, 'failed_at' => $job->failed_at])->values(),
        ]);
    }

    public function retry(string $uuid): RedirectResponse
    {
        if (blank(config('services.brevo.key')) || blank(config('services.brevo.sender_email'))) {
            throw ValidationException::withMessages(['email' => 'Configure the Brevo API key and sender email before retrying.']);
        }
        DB::connection(config('queue.connections.database.connection'))->transaction(function () use ($uuid): void {
            $job = DB::connection(config('queue.connections.database.connection'))->table('failed_jobs')->where('uuid', $uuid)->lockForUpdate()->first();
            abort_if($job === null, 404);
            abort_unless($this->isApplicationEmailFailure($job), 422);
            Artisan::call('queue:retry', ['id' => [$uuid], '--no-interaction' => true]);
        });

        return redirect()->route('email-status')->with('success', 'Application email queued for another attempt. Refresh the status in a moment, then check the recipient’s inbox.');
    }

    private function isApplicationEmailFailure(object $job): bool
    {
        $payload = json_decode($job->payload, true);

        return $job->connection === 'database'
            && data_get($payload, 'displayName') === MembershipApplicationSubmitted::class
            && data_get($payload, 'data.commandName') === SendQueuedNotifications::class;
    }

    private function failureReason(string $exception): string
    {
        $message = strtolower($exception);

        return match (true) {
            str_contains($message, 'idempotency key exceeds char limit') => 'Email request format error; deploy the latest update and retry',
            str_contains($message, 'unrecognised ip'), str_contains($message, 'unrecognized ip'), str_contains($message, 'unauthorized ip') => 'Server IP needs authorization in Brevo',
            str_contains($message, 'brevo sender must be configured') => 'Sender email not configured',
            str_contains($message, 'status code 401'), str_contains($message, 'key not found'), str_contains($message, 'invalid api key') => 'Brevo rejected the API key',
            str_contains($message, 'access denied for resource'), str_contains($message, 'status code 403') => 'Brevo denied access: check API permissions and authorized IPs',
            str_contains($message, 'status code 429') => 'Brevo sending or request limit reached',
            str_contains($message, 'status code 400') => 'Brevo rejected the request: check sender verification and account activation',
            str_contains($message, 'connectionexception'), str_contains($message, 'curl error') => 'Cannot connect to Brevo',
            default => 'Email job failed; inspect the hosting logs privately',
        };
    }
}
