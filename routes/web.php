<?php

use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])
    ->name('password.reset');
Route::middleware('throttle:password-reset')
    ->post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->name('password.update');
