<?php

namespace App\Console\Commands;

use App\Models\MembershipTransaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('looksmaxxing:expire')]
#[Description('Expire overdue applications that have no recorded payments')]
class ExpireApplications extends Command
{
    public function handle(): int
    {
        $count = MembershipTransaction::query()->where('status', 'pending')->where('payment_due_at', '<=', now())->whereDoesntHave('payments')->update(['status' => 'expired']);
        $this->info('Expired transactions: '.$count);

        return self::SUCCESS;
    }
}
