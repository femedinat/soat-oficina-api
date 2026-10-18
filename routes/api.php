<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Oficina\Customer\Interfaces\Http\Controller\VehicleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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
