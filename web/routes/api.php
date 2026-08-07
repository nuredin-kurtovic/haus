<?php

use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\SurchargeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Javno, bez auth-a.
    Route::get('packages', [PackageController::class, 'index']);
    Route::get('cities', [CityController::class, 'index']);
    Route::get('price-list', [PriceListController::class, 'index']);
    Route::get('surcharges', [SurchargeController::class, 'index']);
    Route::get('settings/public', [PublicSettingsController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', fn (Request $request) => $request->user());
    });
});
