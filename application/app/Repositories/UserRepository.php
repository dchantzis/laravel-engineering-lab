<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public static function getById(int $id): ?User
    {
        return User::find($id);
    }

    public static function paginate(?int $perPage)
    {
        return User::paginate($perPage);
    }

    public static function getByEmail(
        string $email,
        bool $useFirstOrFail = true
    ): ?User {
        $query = User::where('email', '=', $email);

        return ($useFirstOrFail)
            ? $query->firstOrFail()
            : $query->first();
    }
}
