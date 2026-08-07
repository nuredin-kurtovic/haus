<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\Client\AddressChangeController;
use App\Http\Controllers\Api\Client\DashboardController;
use App\Http\Controllers\Api\Client\JobController;
use App\Http\Controllers\Api\Client\ProfileController;
use App\Http\Controllers\Api\Client\SubscriptionController;
use App\Http\Controllers\Api\Dev\FakePaymentController;
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

    // Samo lokalno: simulacija kartičnog ishoda. Kontroler vraca 404 cim
    // gateway nije fake ili je debug ugasen.
    Route::post('dev/fake-payment', [FakePaymentController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [MeController::class, 'show']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('devices', [DeviceController::class, 'store']);

        // Klijentski dio.
        Route::middleware('role:klijent')->prefix('client')->group(function () {
            Route::get('ping', fn () => response()->json(['data' => ['scope' => 'klijent']]));

            Route::get('dashboard', [DashboardController::class, 'show']);

            Route::get('jobs', [JobController::class, 'index']);
            // Prijava kvara je jedina klijentska ruta koja trazi aktivnu pretplatu.
            Route::post('jobs', [JobController::class, 'store'])->middleware('subscription.active');
            Route::get('jobs/{id}', [JobController::class, 'show'])->whereNumber('id');

            Route::get('subscription', [SubscriptionController::class, 'show']);
            Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);

            Route::get('price-list', [PriceListController::class, 'mine']);

            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);

            Route::post('address-change-request', [AddressChangeController::class, 'store']);
        });

        // Dispecerski dio. Sadrzaj dolazi u fazi 5.
        Route::middleware('role:dispecer')->prefix('admin')->group(function () {
            Route::get('ping', fn () => response()->json(['data' => ['scope' => 'dispecer']]));
        });
    });
});
