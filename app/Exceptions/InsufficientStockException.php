<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    private $available;
    private $requested;

    public function __construct(string $productName, float $available, float $requested)
    {
        $this->available = $available;
        $this->requested = $requested;

        parent::__construct(sprintf(
            'No hay stock suficiente para %s. Disponible: %s, solicitado: %s.',
            $productName,
            number_format($available, 3, '.', ''),
            number_format($requested, 3, '.', '')
        ));
    }

    public function getAvailable(): float
    {
        return $this->available;
    }

    public function getRequested(): float
    {
        return $this->requested;
    }
}
