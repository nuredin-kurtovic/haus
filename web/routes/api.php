<?php

use App\Http\Controllers\Api\Admin\BillingController;
use App\Http\Controllers\Api\Admin\CityController as AdminCityController;
use App\Http\Controllers\Api\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\JobController as AdminJobController;
use App\Http\Controllers\Api\Admin\PriceListController as AdminPriceListController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\Admin\SurchargeController as AdminSurchargeController;
use App\Http\Controllers\Api\Admin\TechnicianController as AdminTechnicianController;
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
use App\Http\Controllers\Api\Technician\JobController as TechnicianJobController;
use App\Http\Controllers\Api\Technician\PriceListController as TechnicianPriceListController;
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

        // Serviserski dio. Majstor vidi samo naloge koji su njemu dodijeljeni.
        Route::middleware(['role:majstor', 'technician.profile'])->prefix('technician')->group(function () {
            Route::get('ping', fn () => response()->json(['data' => ['scope' => 'majstor']]));

            Route::get('jobs', [TechnicianJobController::class, 'index']);
            Route::get('jobs/{id}', [TechnicianJobController::class, 'show'])->whereNumber('id');
            Route::post('jobs/{id}/start', [TechnicianJobController::class, 'start'])->whereNumber('id');
            Route::post('jobs/{id}/complete', [TechnicianJobController::class, 'complete'])->whereNumber('id');

            Route::get('price-list', [TechnicianPriceListController::class, 'index']);
        });

        // Dispecerski dio.
        Route::middleware('role:dispecer')->prefix('admin')->group(function () {
            Route::get('ping', fn () => response()->json(['data' => ['scope' => 'dispecer']]));

            Route::get('dashboard', [AdminDashboardController::class, 'show']);

            Route::get('jobs', [AdminJobController::class, 'index']);
            Route::get('jobs/{id}', [AdminJobController::class, 'show'])->whereNumber('id');
            Route::patch('jobs/{id}', [AdminJobController::class, 'update'])->whereNumber('id');
            Route::get('jobs/{id}/notification-preview', [AdminJobController::class, 'notificationPreview'])->whereNumber('id');
            Route::post('jobs/{id}/complete', [AdminJobController::class, 'complete'])->whereNumber('id');
            Route::post('jobs/{id}/warranty-job', [AdminJobController::class, 'warrantyJob'])->whereNumber('id');

            Route::get('clients', [AdminClientController::class, 'index']);
            Route::get('clients/{id}', [AdminClientController::class, 'show'])->whereNumber('id');

            Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);

            Route::get('billing', [BillingController::class, 'index']);
            Route::post('invoices/{id}/refund', [BillingController::class, 'refund'])->whereNumber('id');

            Route::get('price-list', [AdminPriceListController::class, 'index']);
            Route::put('price-list/items/{id}', [AdminPriceListController::class, 'updateItem'])->whereNumber('id');
            Route::post('price-list/publish', [AdminPriceListController::class, 'publish']);

            Route::get('cities', [AdminCityController::class, 'index']);
            Route::post('cities', [AdminCityController::class, 'store']);
            Route::patch('cities/{id}', [AdminCityController::class, 'update'])->whereNumber('id');
            Route::delete('cities/{id}', [AdminCityController::class, 'destroy'])->whereNumber('id');

            Route::get('settings', [AdminSettingsController::class, 'show']);
            Route::put('settings', [AdminSettingsController::class, 'update']);

            Route::get('surcharges', [AdminSurchargeController::class, 'index']);
            Route::get('surcharges/{id}', [AdminSurchargeController::class, 'show'])->whereNumber('id');
            Route::put('surcharges/{id}', [AdminSurchargeController::class, 'update'])->whereNumber('id');

            Route::get('technicians', [AdminTechnicianController::class, 'index']);
            Route::post('technicians', [AdminTechnicianController::class, 'store']);
            Route::patch('technicians/{id}', [AdminTechnicianController::class, 'update'])->whereNumber('id');
            Route::delete('technicians/{id}', [AdminTechnicianController::class, 'destroy'])->whereNumber('id');
        });
    });
});
