<?php

namespace App\Notifications\Channels;

use App\Notifications\MembershipApplicationSubmitted;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrevoChannel
{
    public function send(object $notifiable, MembershipApplicationSubmitted $notification): void
    {
        $sender = config('services.brevo.sender_email');
        if (blank($sender)) {
            throw new RuntimeException('The Brevo sender must be configured.');
        }
        $transaction = $notification->transaction();
        if ($transaction->notification_sent_at !== null) {
            return;
        }
        $recipient = $notifiable->routeNotificationFor('mail', $notification);
        $response = Http::acceptJson()->withHeaders(['api-key' => config('services.brevo.key')])->timeout(10)
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => ['name' => config('services.brevo.sender_name'), 'email' => $sender],
                'to' => [['email' => $recipient, 'name' => $transaction->member->full_name]],
                'subject' => 'LooksMaxxing application received',
                'htmlContent' => view('emails.application', compact('transaction'))->render(),
                'headers' => ['idempotencyKey' => 'membership-'.$transaction->reference_no],
            ])->throw();
        if ($response->successful()) {
            $transaction->update(['notification_sent_at' => now()]);
        }
    }
}
