<?php

namespace App\Services\Auth;

use App\Exceptions\Domain\InvalidCredentials;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginUser
{
    /**
     * Bcrypt hash of a random discarded value, with the same cost as real passwords. Checking it
     * for an unknown email takes as long as for a known one, so timing does not reveal accounts.
     */
    private const DUMMY_HASH = '$2y$12$WAIGBf4aiZ8xlntxXJ8Kb.NQkZYIKv1k4mAPoL4FzTeNj/bLKrBq6';

    public function __construct(private UserSession $session) {}

    /**
     * @throws InvalidCredentials
     */
    public function handle(string $email, string $password): User
    {
        $user = User::query()->where('email', $email)->first(['id', 'name', 'email', 'role', 'password']);
        $passwordMatches = Hash::check($password, $user->password ?? self::DUMMY_HASH);

        if ($user === null || ! $passwordMatches) {
            throw new InvalidCredentials;
        }

        $this->session->start($user);

        return $user;
    }
}
