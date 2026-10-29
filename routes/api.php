<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Oficina\Catalog\Interfaces\Http\ServiceController;
use Oficina\Customer\Interfaces\Http\Controller\VehicleController;
use Oficina\Customer\Interfaces\Http\CustomerController;
use Oficina\Identity\Interfaces\Http\AuthController;
use Oficina\Identity\Interfaces\Http\UserController;
use Oficina\Inventory\Interfaces\Http\InventoryItemController;
use Oficina\ServiceOrder\Interfaces\Http\ServiceOrderController;
use Oficina\ServiceOrder\Interfaces\Http\TrackServiceOrderController;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Acompanhamento público da OS: número da OS + CPF/CNPJ, sem login, com limite por IP contra enumeração.
Route::get('public/service-orders/{order}', TrackServiceOrderController::class)
    ->whereUuid('order')
    ->middleware('throttle:20,1');

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

    // Catálogo de serviços: todos os perfis consultam; administrador cadastra e altera.
    Route::prefix('services')->controller(ServiceController::class)->whereUuid(['service', 'part'])->group(function (): void {
        Route::middleware('role:administrator,mechanic,customer')->group(function (): void {
            Route::get('/', 'index');
            Route::get('{service}', 'show');
        });

        Route::middleware('role:administrator')->group(function (): void {
            Route::post('/', 'store');
            Route::put('{service}', 'update');
            Route::patch('{service}/price', 'changePrice');
            Route::patch('{service}/estimated-time', 'adjustEstimatedTime');
            Route::put('{service}/standard-parts', 'linkPart');
            Route::delete('{service}/standard-parts/{part}', 'unlinkPart');
            Route::patch('{service}/activate', 'activate');
            Route::delete('{service}', 'destroy');
        });
    });

    // Ordem de Serviço: oficina conduz o fluxo; o cliente acompanha e decide só as próprias OS.
    Route::prefix('service-orders')->controller(ServiceOrderController::class)->whereUuid(['order', 'line'])->group(function (): void {
        Route::get('metrics/execution-time', 'executionTime')->middleware('role:administrator');

        Route::middleware('role:administrator,mechanic,customer')->group(function (): void {
            Route::get('/', 'index');
            Route::get('{order}', 'show');
        });

        Route::middleware('role:administrator,customer')->group(function (): void {
            Route::post('{order}/budget/approve', 'approve');
            Route::post('{order}/budget/reject', 'reject');
            Route::post('{order}/additional-repairs/authorize', 'authorizeAdditional');
            Route::post('{order}/additional-repairs/refuse', 'refuseAdditional');
        });

        Route::middleware('role:administrator,mechanic')->group(function (): void {
            Route::post('{order}/services', 'addService');
            Route::delete('{order}/services/{line}', 'removeService');
            Route::post('{order}/parts', 'addPart');
            Route::delete('{order}/parts/{line}', 'removePart');
            Route::post('{order}/budget/send', 'sendBudget');
            Route::post('{order}/budget/return-to-diagnosis', 'returnToDiagnosis');
            Route::post('{order}/additional-repairs', 'requestAdditionalRepair');
            Route::post('{order}/finish', 'finish');
        });

        Route::middleware('role:administrator')->group(function (): void {
            Route::post('/', 'store');
            Route::post('{order}/deliver', 'deliver');
            Route::post('{order}/cancel', 'cancel');
        });
    });
});
