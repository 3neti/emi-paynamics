<?php

namespace LBHurtado\EmiPaynamicsConstellation\Exceptions;

use RuntimeException;
use Throwable;

class ConstellationRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        protected array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function context(): array
    {
        return $this->context;
    }
}