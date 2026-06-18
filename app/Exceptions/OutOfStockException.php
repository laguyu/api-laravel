<?php

namespace App\Exceptions;

use Exception;

class OutOfStockException extends Exception
{
    public function __construct(string $message = 'Uno o más productos no tienen suficiente stock disponible.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
