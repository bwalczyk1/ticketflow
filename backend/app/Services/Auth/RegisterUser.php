<?php

namespace App\Services\Auth;

use App\Models\User;

class RegisterUser
{
    public function __construct(private UserSession $session) {}

    /**
     * New accounts always get the default ATTENDEE role, because role is not fillable.
     */
    public function handle(string $name, string $email, string $password): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);

        $this->session->start($user);

        return $user;
    }
}
