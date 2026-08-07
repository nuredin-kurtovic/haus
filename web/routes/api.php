<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\SurchargeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Javno, bez auth-a.
    Route::get('packages', [PackageController::class, 'index']);
    Route::get('cities', [CityController::class, 'index']);
    Route::get('price-list', [PriceListController::class, 'index']);
    Route::get('surcharges', [SurchargeController::class, 'index']);
    Route::get('settings/public', [PublicSettingsController::class, 'show']);

    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Masinski poziv gatewaya, ulaznica je potpis a ne Bearer token.
    Route::post('webhooks/monri', [PaymentWebhookController::class, 'monri']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [MeController::class, 'show']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('devices', [DeviceController::class, 'store']);

        // Klijentski dio. Sadrzaj dolazi u fazi 3 i 4.
        Route::middleware('role:klijent')->prefix('client')->group(function () {
            Route::get('ping', fn () => response()->json(['data' => ['scope' => 'klijent']]));
        });

        // Dispecerski dio. Sadrzaj dolazi u fazi 5.
        Route::middleware('role:dispecer')->prefix('admin')->group(function () {
            Route::get('ping', fn () => response()->json(['data' => ['scope' => 'dispecer']]));
        });
    });
});
