<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Region (Kraj) DTO
 */
final class Region
{
    public function __construct(
        public readonly string $regionId,
        public readonly string $regionName,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            regionId: (string) $data['regionId'],
            regionName: (string) $data['regionName'],
        );
    }
}
