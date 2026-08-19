<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::view('/login', 'pages.auth.login')->name('login');
Route::view('/dashboard', 'pages.dashboard.index')->name('dashboard');
Route::view('/patients', 'pages.patients.index')
    ->name('web.patients.index');

Route::view('/patients/{patient}', 'pages.patients.show')
    ->whereNumber('patient')
    ->name('web.patients.show');