<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Oficina\Customer\Interfaces\Http\Controller\VehicleController;
use Oficina\Customer\Interfaces\Http\CustomerController;
use Oficina\Identity\Interfaces\Http\AuthController;
use Oficina\Identity\Interfaces\Http\UserController;
use Oficina\Inventory\Interfaces\Http\InventoryItemController;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::middleware('role:administrator,mechanic')->group(function (): void {
        Route::get('customers/document/{document}', [CustomerController::class, 'showByDocument'])
            ->where('document', '.*');
        Route::apiResource('customers', CustomerController::class)
            ->only(['index', 'show'])
            ->parameters(['customers' => 'customer']);

        Route::prefix('vehicles')->group(function (): void {
            Route::get('/', [VehicleController::class, 'index']);
            Route::get('/plate/{plate}', [VehicleController::class, 'showByLicensePlate']);
            Route::get('/{id}', [VehicleController::class, 'show'])->whereUuid('id');
        });
    });

    Route::middleware('role:administrator')->group(function (): void {
        Route::patch('customers/{customer}/contact', [CustomerController::class, 'updateContact']);
        Route::patch('customers/{customer}/activate', [CustomerController::class, 'activate']);
        Route::apiResource('customers', CustomerController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['customers' => 'customer']);

        Route::prefix('vehicles')->group(function (): void {
            Route::post('/', [VehicleController::class, 'store']);
            Route::put('/{id}', [VehicleController::class, 'update'])->whereUuid('id');
            Route::patch('/{id}/mileage', [VehicleController::class, 'registerMileage'])->whereUuid('id');
            Route::patch('/{id}/transfer', [VehicleController::class, 'transferOwnership'])->whereUuid('id');
            Route::patch('/{id}/activate', [VehicleController::class, 'activate'])->whereUuid('id');
            Route::delete('/{id}', [VehicleController::class, 'destroy'])->whereUuid('id');
        });
    });

    Route::post('users', [UserController::class, 'store'])->middleware('role:administrator');

    // Peças e insumos: mecânico consulta; administrador cadastra e movimenta o estoque. Id fora do formato UUID = 404.
    Route::prefix('parts')->controller(InventoryItemController::class)->whereUuid('part')->group(function (): void {
        Route::middleware('role:administrator,mechanic')->group(function (): void {
            Route::get('/', 'index');
            Route::get('restock', 'restock');
            Route::get('{part}', 'show');
        });

        Route::middleware('role:administrator')->group(function (): void {
            Route::post('/', 'store');
            Route::put('{part}', 'update');
            Route::patch('{part}/price', 'changePrice');
            Route::post('{part}/receipts', 'receive');
            Route::post('{part}/adjustments', 'adjust');
            Route::get('{part}/movements', 'movements');
            Route::patch('{part}/activate', 'activate');
            Route::delete('{part}', 'destroy');
        });
    });
});
