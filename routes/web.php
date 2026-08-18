<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::view('/login', 'pages.auth.login')->name('login');
Route::view('/dashboard', 'pages.dashboard.index')->name('dashboard');
