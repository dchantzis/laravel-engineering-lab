<?php

namespace App\Services;

use App\DataTransferObjects\User\User as UserDto;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Str;

class UserService
{
    public function store(UserDto $userDto): User
    {
        $password = Str::random(32);

        $user = new User();

        $user->name = $userDto->name;
        $user->email = $userDto->email;
        $user->password = $password;
        $user->is_admin = $userDto->isAdmin;

        $user->save();

        $user->passwordUnhashed = $password;

        return $user;
    }

    public function update(
        User $user,
        UserDto $userDto
    ): User
    {
        $user->name = $userDto->name;
        $user->email = $userDto->email;
        $user->is_admin = $userDto->isAdmin;

        $user->save();

        return $user;
    }

    public function find(int $id): ?User
    {
        return UserRepository::getById($id);
    }

    public function paginate(?int $perPage)
    {
        return UserRepository::paginate($perPage);
    }

    public function findUserByEmail(string $email): ?User
    {
        return UserRepository::getByEmail($email, false);
    }
}
