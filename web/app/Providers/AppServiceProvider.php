<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\MonriGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Driver placanja se bira konfiguracijom, nikad kodom u kontroleru.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function (): PaymentGateway {
            $driver = (string) config('services.haus.payment_gateway', 'fake');

            return match ($driver) {
                'fake' => new FakeGateway,
                'monri' => new MonriGateway,
                default => throw new InvalidArgumentException(
                    "Driver placanja [{$driver}] nije implementiran."
                ),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
