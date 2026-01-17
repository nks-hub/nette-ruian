<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Address validation result DTO
 */
final readonly class ValidateResult
{
    public const string STATUS_ERROR = 'ERROR';
    public const string STATUS_NOT_FOUND = 'NOT_FOUND';
    public const string STATUS_POSSIBLE = 'POSSIBLE';
    public const string STATUS_MATCH = 'MATCH';

    public function __construct(
        public string $status,
        public ?string $message,
        public ?ValidatedPlace $place,
    ) {
    }

    /**
     * @param array{status: string, message?: string|null, place?: array<string, mixed>|null} $data
     */
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
