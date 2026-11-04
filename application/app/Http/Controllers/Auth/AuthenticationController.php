<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Services\AuthenticationService;

class AuthenticationController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authenticationService
    ) {}
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(
        LoginRequest $request,
    ): RedirectResponse
    {
        if (! $this->authenticationService->attemptAdminLogin($request->validated())) {
            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(
        Request $request
    ): RedirectResponse
    {
        $this->authenticationService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
