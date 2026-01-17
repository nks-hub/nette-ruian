<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Municipality (Obec) DTO
 */
final class Municipality
{
    public function __construct(
        public readonly int $municipalityId,
        public readonly string $municipalityName,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            municipalityId: (int) $data['municipalityId'],
            municipalityName: (string) $data['municipalityName'],
        );
    }
}
