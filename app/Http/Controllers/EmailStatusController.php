<?php

namespace App\Http\Controllers;

use App\Models\MembershipTransaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        ]);
    }

    private function failureReason(string $exception): string
    {
        $message = strtolower($exception);

        return match (true) {
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
