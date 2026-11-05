<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserSession
{
    public function start(User $user): void
    {
        Auth::guard('web')->login($user);
        session()->regenerate();
    }

    public function end(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
