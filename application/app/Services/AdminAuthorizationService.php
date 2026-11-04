<?php

namespace App\Services;

use App\Models\User;

class AdminAuthorizationService
{
    public function isAdmin(?User $user): bool
    {
        return $user?->is_admin === true;
    }
}
