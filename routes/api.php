<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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
    Route::apiResource('customers', CustomerController::class)
        ->only(['index', 'show'])
        ->middleware('role:administrator,mechanic')
        ->parameters(['customers' => 'customer']);

    Route::apiResource('customers', CustomerController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:administrator')
        ->parameters(['customers' => 'customer']);

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
