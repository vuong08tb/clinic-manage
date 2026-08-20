<?php

use Illuminate\Support\Facades\Route;

// Each feature has exactly one page: the list page. Create/edit/detail run in modals on that page.
Route::redirect('/', '/dashboard');

Route::view('/login', 'pages.auth.login')->name('login');
Route::view('/dashboard', 'pages.dashboard.index')->name('dashboard');

Route::view('/patients', 'pages.patients.index')
    ->name('web.patients.index');

Route::view('/appointments', 'pages.appointments.index')
    ->name('web.appointments.index');

Route::view('/examinations', 'pages.examinations.index')
    ->name('web.examinations.index');

Route::view('/prescriptions', 'pages.prescriptions.index')
    ->name('web.prescriptions.index');
