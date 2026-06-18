<?php

namespace App\DTOs;

class PaymentResult
{
    public function __construct(
        public bool $success,
        public ?string $transactionId = null,
        public ?string $errorMessage = null
    ) {}
}
