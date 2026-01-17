<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Address validation result DTO
 */
final class ValidateResult
{
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_NOT_FOUND = 'NOT_FOUND';
    public const STATUS_POSSIBLE = 'POSSIBLE';
    public const STATUS_MATCH = 'MATCH';

    public function __construct(
        public readonly string $status,
        public readonly ?string $message,
        public readonly ?ValidatedPlace $place,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: (string) $data['status'],
            message: $data['message'] ?? null,
            place: isset($data['place']) ? ValidatedPlace::fromArray($data['place']) : null,
        );
    }

    public function isMatch(): bool
    {
        return $this->status === self::STATUS_MATCH;
    }

    public function isPossible(): bool
    {
        return $this->status === self::STATUS_POSSIBLE;
    }

    public function isFound(): bool
    {
        return $this->status === self::STATUS_MATCH || $this->status === self::STATUS_POSSIBLE;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function isNotFound(): bool
    {
        return $this->status === self::STATUS_NOT_FOUND;
    }
}
