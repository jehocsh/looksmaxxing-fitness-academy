<?php

namespace App\Console\Commands;

use App\Services\MembershipService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('looksmaxxing:expire')]
#[Description('Expire overdue applications that have no recorded payments')]
class ExpireApplications extends Command
{
    public function handle(MembershipService $service): int
    {
        $count = $service->expireUnpaid();
        $this->info('Expired transactions: '.$count);

        return self::SUCCESS;
    }
}
