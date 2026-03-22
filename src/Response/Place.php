<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Response;

/**
 * Place (Address point) DTO
 */
final readonly class Place
{
    use HouseNumberFormatTrait;

    public function __construct(
        public ?string $cp,
        public ?string $co,
        public ?string $ce,
        public int $zip,
        public int $placeId,
    ) {
    }

    /**
     * @param array{placeCp?: string|int|null, placeCo?: string|int|null, placeCe?: string|int|null, placeZip: int|string, placeId: int|string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cp: isset($data['placeCp']) ? (string) $data['placeCp'] : null,
            co: isset($data['placeCo']) ? (string) $data['placeCo'] : null,
            ce: isset($data['placeCe']) ? (string) $data['placeCe'] : null,
            zip: (int) $data['placeZip'],
            placeId: (int) $data['placeId'],
        );
    }
}
