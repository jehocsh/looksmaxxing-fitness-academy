<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('looksmaxxing:bootstrap-admin')]
#[Description('Initialize the first administrator from private hosting environment variables')]
class BootstrapAdmin extends Command
{
    public function handle(): int
    {
        if (User::query()->where('is_admin', true)->exists()) {
            $this->info('Administrator already configured.');

            return self::SUCCESS;
        }
        $data = ['email' => config('gym.bootstrap_admin.email'), 'password' => config('gym.bootstrap_admin.password')];
        if (blank($data['email']) && blank($data['password'])) {
            $this->warn('Set ADMIN_EMAIL and ADMIN_PASSWORD privately to initialize staff access.');

            return self::SUCCESS;
        }
        $validator = Validator::make($data, ['email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:12']]);
        if ($validator->fails()) {
            $this->error('Administrator setup requires an unused valid email and a password of at least 12 characters.');

            return self::FAILURE;
        }
        $user = new User;
        $user->name = 'LooksMaxxing Administrator';
        $user->email = strtolower(trim($data['email']));
        $user->password = $data['password'];
        $user->is_admin = true;
        $user->save();
        $this->info('First administrator initialized. You may remove the bootstrap password from hosting settings.');

        return self::SUCCESS;
    }
}
