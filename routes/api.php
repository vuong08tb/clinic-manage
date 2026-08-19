<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\ExaminationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Login is public because the client does not have a Bearer token yet.
Route::post('/login', [AuthController::class, 'login']);

// Self-service authentication endpoints only require a valid Sanctum token.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// Business endpoints require both authentication and an RBAC permission.
Route::middleware(['auth:sanctum', 'permission'])->group(function (): void {
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    Route::apiResource('appointments', AppointmentController::class)
        ->only(['index', 'store', 'show', 'update']);
    Route::apiResource('examinations', ExaminationController::class)
        ->only(['index', 'store', 'show', 'update']);
    Route::apiResource('doctors', DoctorController::class);
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('specialties', SpecialtyController::class);
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
    Route::apiResource('users', UserController::class);
});
