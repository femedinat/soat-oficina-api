<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Oficina\Customer\Interfaces\Http\Controller\VehicleController;
use Oficina\Customer\Interfaces\Http\CustomerController;
use Oficina\Identity\Interfaces\Http\AuthController;
use Oficina\Identity\Interfaces\Http\UserController;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::get('customers/document/{document}', [CustomerController::class, 'showByDocument'])
        ->where('document', '.*');
    Route::patch('customers/{customer}/contact', [CustomerController::class, 'updateContact']);
    Route::patch('customers/{customer}/activate', [CustomerController::class, 'activate']);
    Route::apiResource('customers', CustomerController::class)->parameters(['customers' => 'customer']);

    Route::prefix('vehicles')->group(function (): void {
        Route::get('/', [VehicleController::class, 'index']);
        Route::post('/', [VehicleController::class, 'store']);
        Route::get('/plate/{plate}', [VehicleController::class, 'showByLicensePlate']);
        Route::get('/{id}', [VehicleController::class, 'show'])->whereUuid('id');
        Route::put('/{id}', [VehicleController::class, 'update'])->whereUuid('id');
        Route::patch('/{id}/mileage', [VehicleController::class, 'registerMileage'])->whereUuid('id');
        Route::patch('/{id}/transfer', [VehicleController::class, 'transferOwnership'])->whereUuid('id');
        Route::patch('/{id}/activate', [VehicleController::class, 'activate'])->whereUuid('id');
        Route::delete('/{id}', [VehicleController::class, 'destroy'])->whereUuid('id');
    });

    Route::post('users', [UserController::class, 'store'])->middleware('role:administrator');
});
