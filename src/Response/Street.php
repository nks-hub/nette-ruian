<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Street DTO
 */
final class Street
{
    public function __construct(
        public readonly ?string $streetName,
        public readonly ?string $streetLessPartName,
    ) {
    }

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
