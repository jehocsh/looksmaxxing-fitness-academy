<?php

namespace App\Notifications;

use App\Models\MembershipTransaction;
use App\Notifications\Channels\BrevoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public int $transactionId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return filled(config('services.brevo.key')) ? [BrevoChannel::class] : ['mail'];
    }

    public function transaction(): MembershipTransaction
    {
        return MembershipTransaction::query()->with('member')->findOrFail($this->transactionId);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $transaction = $this->transaction();

        return (new MailMessage)->subject('LooksMaxxing application received')
            ->greeting('Hello '.$transaction->member->full_name.',')
            ->line('Your membership application has been received.')
            ->line('Reference: '.$transaction->reference_no)
            ->line('Plan: '.$transaction->level_name)
            ->line('Amount due: PHP '.number_format($transaction->total_cents / 100, 2))
            ->line('Your application is pending payment and staff approval.')
            ->line('Keep your reference number and contact the academy to arrange payment.')
            ->salutation('LooksMaxxing Fitness Academy');
    }
}
