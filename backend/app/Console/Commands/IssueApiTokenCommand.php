<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\IssueApiToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ticketflow:token {email : Email of the user who gets the token}')]
#[Description('Issue a 60 minute API token for "Try it" in /docs/api and scripts (local environment only)')]
class IssueApiTokenCommand extends Command
{
    public function handle(IssueApiToken $issueApiToken): int
    {
        if (! $this->laravel->environment('local')) {
            $this->error('This command works only when APP_ENV=local.');

            return self::FAILURE;
        }

        $user = User::firstWhere('email', $this->argument('email'));

        if ($user === null) {
            $this->error('No user with this email.');

            return self::FAILURE;
        }

        $this->line($issueApiToken->handle($user));

        return self::SUCCESS;
    }
}
