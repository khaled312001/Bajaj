<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LoginLink extends Command
{
    protected $signature = 'login:link {username? : defaults to the first active admin} {--revoke : disable the link instead of issuing a new one}';

    protected $description = 'Issue (or revoke) a personal token login link — a new link replaces the old one';

    public function handle(): int
    {
        $username = $this->argument('username');
        $user = $username
            ? User::whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->first()
            : User::where('role', User::ROLE_ADMIN)->where('is_active', true)->orderBy('id')->first();

        if (! $user) {
            $this->error('User not found.');

            return 1;
        }

        if ($this->option('revoke')) {
            $user->forceFill(['login_token_hash' => null])->saveQuietly();
            $this->info("Login link revoked for {$user->username}.");

            return 0;
        }

        $token = Str::random(64);
        $user->forceFill(['login_token_hash' => hash('sha256', $token)])->saveQuietly();
        $this->info("Login link for {$user->username}:");
        $this->line(route('login.token', $token));

        return 0;
    }
}
