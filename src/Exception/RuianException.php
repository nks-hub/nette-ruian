<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Exception;

/**
 * Base exception for RUIAN API
 */
class RuianException extends \RuntimeException
{
    public static function create(string $message, ?\Throwable $previous = null): static
    {
        return new static($message, 0, $previous);
    }
}
