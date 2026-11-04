<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class AuthenticationService
{
    public function __construct(
        private readonly AdminAuthorizationService $adminAuthorizationService
    ) {}

    public function attemptAdminLogin(array $credentials): bool
    {
        $guard = Auth::guard('web');

        if (! $guard->attempt($credentials)) {
            return false;
        }

        if (! $this->adminAuthorizationService->isAdmin($guard->user())) {
            $guard->logout();

            return false;
        }

        return true;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }
}
