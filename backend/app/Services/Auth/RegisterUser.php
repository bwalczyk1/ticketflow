<?php

namespace App\Services\Auth;

use App\Exceptions\Domain\EmailTaken;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class RegisterUser
{
    public function __construct(private UserSession $session) {}

    /**
     * New accounts always get the default ATTENDEE role, because role is not fillable.
     * The unique email index decides, so two parallel registrations cannot both succeed.
     *
     * @throws EmailTaken
     */
    public function handle(string $name, string $email, string $password): User
    {
        try {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        } catch (UniqueConstraintViolationException) {
            throw new EmailTaken;
        }

        $this->session->start($user);

        return $user;
    }
}
