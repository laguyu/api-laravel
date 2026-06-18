<?php

namespace App\Providers;

use App\Gateways\Contracts\PaymentGatewayInterface;
use App\Gateways\Payment\PayPalPaymentGateway;
use App\Gateways\Payment\StripePaymentGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $gateway = config('services.payment.gateway', 'stripe');

            return match (strtolower($gateway)) {
                'paypal' => $app->make(PayPalPaymentGateway::class),
                default => $app->make(StripePaymentGateway::class),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
