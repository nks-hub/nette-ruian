<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Municipality (Obec) DTO
 */
final readonly class Municipality
{
    public function __construct(
        public int $municipalityId,
        public string $municipalityName,
    ) {
    }

    /**
     * @param array{municipalityId: int|string, municipalityName: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            municipalityId: (int) $data['municipalityId'],
            municipalityName: (string) $data['municipalityName'],
        );
    }
}
