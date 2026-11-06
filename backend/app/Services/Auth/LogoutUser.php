<?php

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUser
{
    public function __construct(private UserSession $session) {}

    /**
     * A caller using an API token has no session, so logging out means revoking that token.
     */
    public function handle(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->session->end();
    }
}
