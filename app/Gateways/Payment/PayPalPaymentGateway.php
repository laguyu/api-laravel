<?php

namespace App\Gateways\Payment;

use App\DTOs\PaymentResult;
use App\Gateways\Contracts\PaymentGatewayInterface;

class PayPalPaymentGateway implements PaymentGatewayInterface
{
    public function charge(float $amount, string $paymentMethod): PaymentResult
    {
        // Mocking PayPal REST SDK integration
        // In a production app, you would make an API request to PayPal v2/checkout/orders

        // Simulate a payment failure for testing purposes
        if ($paymentMethod === 'paypal_fail_method') {
            return new PaymentResult(
                success: false,
                errorMessage: 'PayPal: Cuenta no verificada o fondos insuficientes.'
            );
        }

        return new PaymentResult(
            success: true,
            transactionId: 'PAYID-paypal_'.uniqid()
        );
    }
}
