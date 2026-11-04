<?php

use App\Http\Controllers\Auth\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest:web')
    ->group(function () {
    Route::get('/login', [
        AuthenticationController::class, 'showLoginForm'
    ])->name('login');

    Route::post('/login', [
        AuthenticationController::class, 'login'
    ])->name('login.submit');
});

Route::post('/logout', [AuthenticationController::class, 'logout'])
    ->middleware('auth:web')
    ->name('logout');
