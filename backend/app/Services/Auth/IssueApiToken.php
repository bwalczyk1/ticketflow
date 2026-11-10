<?php

namespace App\Services\Auth;

use App\Models\User;

class IssueApiToken
{
    private const LIFETIME_MINUTES = 60;

    public function handle(User $user): string
    {
        return $user->createToken('cli', ['*'], now()->addMinutes(self::LIFETIME_MINUTES))->plainTextToken;
    }
}
