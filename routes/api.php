<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission'])->group(function (): void {
    // Register protected business API routes here.
});
