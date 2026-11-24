<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('looksmaxxing:admin {email?} {--name=LooksMaxxing Administrator} {--generate : Generate a strong password}')]
#[Description('Create an administrator without public registration or shared default credentials')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?? $this->ask('Administrator email'))));
        $password = $this->option('generate') ? Str::password(24) : $this->secret('Password (at least 12 characters)');
        $validator = Validator::make(['email' => $email, 'password' => $password], ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'string', 'min:12']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = new User;
        $user->name = $this->option('name');
        $user->email = $email;
        $user->password = $password;
        $user->is_admin = true;
        $user->save();
        $this->info('Administrator created: '.$email);
        if ($this->option('generate')) {
            $this->line('Generated password: '.$password);
        }

        return self::SUCCESS;
    }
}
