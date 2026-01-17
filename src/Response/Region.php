<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Region (Kraj) DTO
 */
final readonly class Region
{
    public function __construct(
        public string $regionId,
        public string $regionName,
    ) {
    }

    /**
     * @param array{regionId: string|int, regionName: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            regionId: (string) $data['regionId'],
            regionName: (string) $data['regionName'],
        );
    }
}
