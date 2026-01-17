<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Exception;

/**
 * API communication exception
 */
final class RuianApiException extends RuianException
{
    public static function connectionFailed(): self
    {
        return new self('Failed to connect to RUIAN API');
    }

    public static function serverError(): self
    {
        return new self('RUIAN API server error');
    }

    public static function missingParameters(): self
    {
        return new self('Missing required parameters');
    }

    public static function unexpectedStatus(int $code): self
    {
        return new self("Unexpected HTTP status: {$code}");
    }

    public static function invalidJson(string $error): self
    {
        return new self("Invalid JSON response: {$error}");
    }
}
