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
Route::view('/appointments', 'pages.appointments.index')
    ->name('web.appointments.index');
Route::view('/examinations', 'pages.examinations.index')
    ->name('web.examinations.index');

Route::view('/examinations/create', 'pages.examinations.create')
    ->name('web.examinations.create');

Route::view('/examinations/{examination}', 'pages.examinations.show')
    ->whereNumber('examination')
    ->name('web.examinations.show');