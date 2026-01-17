<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Exception;

/**
 * Authentication exception (invalid API key)
 */
final class RuianAuthException extends RuianException
{
    private const string DEFAULT_MESSAGE = 'Invalid API key';

    public static function invalidApiKey(): self
    {
        return new self(self::DEFAULT_MESSAGE);
    }
}
