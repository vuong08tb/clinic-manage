<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\ExaminationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Login is public because the client does not have a Bearer token yet.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

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
    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus']);
    Route::apiResource('invoices', InvoiceController::class)
        ->only(['index', 'store', 'show', 'update']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/paypal/client-token', [PaymentController::class, 'clientToken']);
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);
    Route::post('/payments/{payment}/capture', [PaymentController::class, 'capture']);
    Route::get('/medicines/low-stock', [MedicineController::class, 'lowStock']);
    Route::patch('/medicines/{medicine}/stock', [MedicineController::class, 'adjustStock']);
    Route::apiResource('medicines', MedicineController::class);
    Route::apiResource('patients', PatientController::class);
    Route::get('/prescriptions', [PrescriptionController::class, 'index']);
    Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show']);
    Route::post('/prescriptions', [PrescriptionController::class, 'store']);
    Route::match(['put', 'patch'], '/prescriptions/{prescription}', [PrescriptionController::class, 'update']);
    Route::post('/prescriptions/{prescription}/items', [PrescriptionController::class, 'addItem']);
    Route::match(['put', 'patch'], '/prescriptions/{prescription}/items/{item}', [PrescriptionController::class, 'updateItem']);
    Route::delete('/prescriptions/{prescription}/items/{item}', [PrescriptionController::class, 'removeItem']);
    Route::apiResource('specialties', SpecialtyController::class);
    Route::get('/stats', [StatsController::class, 'show']);
    Route::get('/roles', [RoleController::class, 'index']);
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
    Route::apiResource('users', UserController::class);
});
