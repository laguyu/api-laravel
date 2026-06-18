<?php

namespace App\Gateways\Payment;

use App\DTOs\PaymentResult;
use App\Gateways\Contracts\PaymentGatewayInterface;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function charge(float $amount, string $paymentMethod): PaymentResult
    {
        // Mocking Stripe integration
        // In a production app, you would instantiate StripeClient and execute a PaymentIntent:
        // $stripe->paymentIntents->create([...]);

        // Simulate a payment failure for testing purposes
        if ($paymentMethod === 'stripe_fail_method') {
            return new PaymentResult(
                success: false,
                errorMessage: 'Stripe: Fondos insuficientes o tarjeta declinada.'
            );
        }

        return new PaymentResult(
            success: true,
            transactionId: 'ch_stripe_'.uniqid()
        );
    }
}
