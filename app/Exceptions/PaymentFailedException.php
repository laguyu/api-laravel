<?php

namespace App\Exceptions;

use Exception;

class PaymentFailedException extends Exception
{
    public function __construct(string $message = 'El pago no pudo procesarse correctamente.', int $code = 402)
    {
        parent::__construct($message, $code);
    }
}
