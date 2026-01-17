<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Exception;

/**
 * Rate limit exceeded exception
 */
final class RuianRateLimitException extends RuianException
{
    private const string DEFAULT_MESSAGE = 'Rate limit exceeded (1000 requests/hour)';
    private const int DEFAULT_LIMIT = 1000;

    public function __construct(
        string $message = self::DEFAULT_MESSAGE,
        public readonly int $limit = self::DEFAULT_LIMIT,
    ) {
        parent::__construct($message);
    }

    public static function exceeded(int $limit = self::DEFAULT_LIMIT): self
    {
        return new self(limit: $limit);
    }
}
