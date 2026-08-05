<?php

use App\Http\Controllers\AuthController;
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
    // Register protected business API routes here.
});
