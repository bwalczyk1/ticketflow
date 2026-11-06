<?php

namespace App\Exceptions\Domain;

class OwnAdminRoleProtected extends DomainException
{
    public function __construct(string $message = 'You cannot remove your own ADMIN role')
    {
        parent::__construct($message, 'OWN_ADMIN_ROLE_PROTECTED', 409);
    }
}
