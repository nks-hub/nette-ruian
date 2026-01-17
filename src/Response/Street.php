<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Street DTO
 */
final readonly class Street
{
    public function __construct(
        public ?string $streetName,
        public ?string $streetLessPartName,
    ) {
    }

    /**
     * @param array{streetName?: string|null, streetLessPartName?: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            streetName: isset($data['streetName']) ? (string) $data['streetName'] : null,
            streetLessPartName: isset($data['streetLessPartName']) ? (string) $data['streetLessPartName'] : null,
        );
    }

    /**
     * Get display name (street name or part name)
     */
    public function getDisplayName(): string
    {
        return $this->streetName ?? $this->streetLessPartName ?? '';
    }
}
