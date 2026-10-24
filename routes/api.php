<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Oficina\Customer\Interfaces\Http\CustomerController;
use Oficina\Identity\Interfaces\Http\AuthController;
use Oficina\Identity\Interfaces\Http\UserController;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::apiResource('customers', CustomerController::class)
        ->only(['index', 'show'])
        ->middleware('role:administrator,mechanic')
        ->parameters(['customers' => 'customer']);

    Route::apiResource('customers', CustomerController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:administrator')
        ->parameters(['customers' => 'customer']);

    Route::post('users', [UserController::class, 'store'])->middleware('role:administrator');
});
