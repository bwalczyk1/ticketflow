<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Exceptions\Domain\OwnAdminRoleProtected;
use App\Models\User;

class ChangeUserRole
{
    public function handle(User $admin, string $userId, Role $role): User
    {
        $user = User::findOrFail($userId, ['id', 'name', 'email', 'role']);

        if ($user->is($admin) && $role !== Role::Admin) {
            throw new OwnAdminRoleProtected;
        }

        $user->role = $role;
        $user->save();

        return $user;
    }
}
