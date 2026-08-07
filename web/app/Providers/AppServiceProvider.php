<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payments\FakeGateway;
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
                // Monri driver stize u fazi 6.
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
