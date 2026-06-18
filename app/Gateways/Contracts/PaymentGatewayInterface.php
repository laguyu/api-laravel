<?php

namespace App\Gateways\Contracts;

use App\DTOs\PaymentResult;

interface PaymentGatewayInterface
{
    /**
     * Charge a specific amount to a payment method.
     */
    public function charge(float $amount, string $paymentMethod): PaymentResult;
}
